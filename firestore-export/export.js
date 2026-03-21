/**
 * Export Firestore collections to JSON for Laravel firestore:migrate.
 * Project: flexana-test
 *
 * Structure: payments (top-level), users (top-level), packages (top-level),
 *            purchased packages: top-level purchasedPackages and/or subcollections under users/{uid}/
 *            (purchasedPackages, purchasedReformPackages, purchasePackages, unlimitedPackages,
 *            reformer, packages — see PURCHASE_COLLECTION_GROUPS; merged by uid + package doc id).
 *
 * 1. serviceAccountKey.json in this folder
 * 2. npm install && npm run export
 * 3. php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json --payment-bookings=storage/app/payment_bookings.json
 */

const admin = require('firebase-admin');
const fs = require('fs');
const path = require('path');

/**
 * Subcollection names queried via collectionGroup.
 * Order matters for field overwrite when merging the same user+package id: later entries win on conflicts.
 * Put `reformer` / `packages` last so `remainingClasses` from those docs overrides purchase-only rows.
 */
const PURCHASE_COLLECTION_GROUPS = [
  'purchasedPackages',
  'purchasedReformPackages',
  'purchasePackages',
  'unlimitedPackages',
  'reformer',
  'packages',
];

const serviceAccountPath = path.join(__dirname, 'serviceAccountKey.json');
if (!fs.existsSync(serviceAccountPath)) {
  console.error('Missing serviceAccountKey.json. Download from Firebase Console → Project Settings → Service accounts → Generate new private key');
  process.exit(1);
}

const serviceAccount = require(serviceAccountPath);
admin.initializeApp({ credential: admin.credential.cert(serviceAccount) });
const db = admin.firestore();

const OUT_DIR = path.join(__dirname, '..', 'storage', 'app');
if (!fs.existsSync(OUT_DIR)) {
  fs.mkdirSync(OUT_DIR, { recursive: true });
}

function serializeValue(value) {
  if (value == null) return null;
  if (value.toDate && typeof value.toDate === 'function') return value.toDate().toISOString();
  if (typeof value === 'object' && value._seconds != null) return new Date(value._seconds * 1000).toISOString();
  return value;
}

function docToPlain(doc) {
  const data = doc.data();
  const out = { id: doc.id };
  for (const [key, value] of Object.entries(data)) {
    out[key] = serializeValue(value);
  }
  return out;
}

/**
 * Align with frontend: yoga/reformer use purchaseDate; unlimited may use startDate.
 * Fills purchaseDate / purchase_date when missing so Laravel migration sees one canonical field.
 */
function normalizePurchaseDateFields(row) {
  const fallbacks = [
    row.purchaseDate,
    row.startDate,
    row.start_date,
    row.createdAt,
    row.purchase_date,
  ];
  let canonical = null;
  for (const v of fallbacks) {
    if (v != null && v !== '') {
      canonical = v;
      break;
    }
  }
  if (canonical != null && (row.purchaseDate == null || row.purchaseDate === '')) {
    row.purchaseDate = canonical;
  }
  if (canonical != null && (row.purchase_date == null || row.purchase_date === '')) {
    row.purchase_date = canonical;
  }
}

async function exportCollection(collectionName, outFile) {
  const snapshot = await db.collection(collectionName).get();
  const docs = snapshot.docs.map(docToPlain);
  const outPath = path.join(OUT_DIR, outFile);
  fs.writeFileSync(outPath, JSON.stringify(docs, null, 2));
  console.log(`Exported ${docs.length} doc(s) from "${collectionName}" → ${outPath}`);
  return docs.length;
}

/**
 * Top-level legacy collections (optional). Each row gets _firestore_path for dedupe with subcollections.
 */
async function fetchTopLevelPurchasedPackageRows() {
  const collectionNames = ['purchasedPackages', 'purchased_packages'];
  const rows = [];
  for (const collectionName of collectionNames) {
    try {
      const snapshot = await db.collection(collectionName).get();
      if (snapshot.empty) {
        console.log(`Top-level "${collectionName}": 0 doc(s)`);
        continue;
      }
      for (const doc of snapshot.docs) {
        const row = docToPlain(doc);
        row._firestore_path = doc.ref.path;
        row._export_source = `top-level:${collectionName}`;
        normalizePurchaseDateFields(row);
        rows.push(row);
      }
      console.log(`Top-level "${collectionName}": ${snapshot.size} doc(s)`);
    } catch (e) {
      console.warn(`Top-level "${collectionName}" failed: ${e.message}`);
    }
  }
  return rows;
}

/**
 * Load users once for merging identity into subcollection rows.
 */
async function loadUsersMap() {
  const usersMap = new Map();
  try {
    const usersSnap = await db.collection('users').get();
    for (const userDoc of usersSnap.docs) {
      usersMap.set(userDoc.id, userDoc.data());
    }
    console.log(`Loaded ${usersMap.size} user(s) for purchased-package identity merge`);
  } catch (e) {
    console.warn(`Could not load users for merge (continuing): ${e.message}`);
  }
  return usersMap;
}

/**
 * Same logical purchase may be split across Firestore paths, e.g.
 * users/{uid}/purchasedReformPackages/{pkgId} (dates) + users/{uid}/reformer/{pkgId} (remainingClasses).
 * Merge by `${uid}::${docId}` so counts and dates land on one row for firestore:migrate.
 */
function mergeRowsByUserAndPackageId(rows) {
  const byKey = new Map();
  for (const row of rows) {
    const uid = row.uid;
    const key = uid ? `${uid}::${String(row.id)}` : `path::${row._firestore_path || ''}`;

    if (!byKey.has(key)) {
      const first = { ...row, _merge_paths: [row._firestore_path] };
      byKey.set(key, first);
      continue;
    }

    const acc = byKey.get(key);
    for (const [k, v] of Object.entries(row)) {
      if (k === '_firestore_path' || k === '_export_source' || k === '_merge_paths') {
        continue;
      }
      if (v === null || v === undefined || v === '') {
        continue;
      }
      acc[k] = v;
    }
    acc._merge_paths.push(row._firestore_path);
    const sources = [acc._export_source, row._export_source].filter(Boolean);
    acc._export_source = [...new Set(sources)].join('+');
  }

  return Array.from(byKey.values()).map((r) => {
    normalizePurchaseDateFields(r);
    return r;
  });
}

function enrichRowWithUser(row, parentId, usersMap) {
  if (parentId && !row.uid) {
    row.uid = parentId;
  }
  const userData = parentId ? usersMap.get(parentId) : null;
  if (userData) {
    row.firstName = row.firstName ?? userData.firstName ?? userData.first_name ?? null;
    row.lastName = row.lastName ?? userData.lastName ?? userData.last_name ?? null;
    row.email = row.email ?? userData.email ?? null;
    row.phone = row.phone ?? userData.phone ?? null;
    row.isVerified = row.isVerified ?? userData.isVerified ?? userData.is_verified ?? null;
  }
  return row;
}

/**
 * Export top-level + all configured collection groups, then merge by uid + package doc id.
 */
async function exportPurchasedPackagesMerged(outFile) {
  const usersMap = await loadUsersMap();
  const allRows = [];

  const topLevelRows = await fetchTopLevelPurchasedPackageRows();
  for (const row of topLevelRows) {
    allRows.push(row);
  }

  for (const cgName of PURCHASE_COLLECTION_GROUPS) {
    try {
      const snapshot = await db.collectionGroup(cgName).get();
      console.log(`Collection group "${cgName}": ${snapshot.docs.length} doc(s)`);
      for (const doc of snapshot.docs) {
        const row = docToPlain(doc);
        row._firestore_path = doc.ref.path;
        row._export_source = cgName;

        const parentId = doc.ref.parent.parent?.id;
        enrichRowWithUser(row, parentId, usersMap);
        allRows.push(row);
      }
    } catch (e) {
      console.warn(`Collection group "${cgName}" failed: ${e.message}`);
    }
  }

  console.log(`Raw rows before uid+package merge: ${allRows.length}`);
  const merged = mergeRowsByUserAndPackageId(allRows);
  console.log(`Merged rows (uid + package id): ${merged.length}`);

  const outPath = path.join(OUT_DIR, outFile);
  fs.writeFileSync(outPath, JSON.stringify(merged, null, 2));
  console.log(`Wrote ${merged.length} doc(s) → ${outPath}`);
  return merged.length;
}

(async () => {
  try {
    await exportPurchasedPackagesMerged('purchased_packages.json');
  } catch (e) {
    console.warn('Purchased packages export failed:', e.message);
    fs.writeFileSync(path.join(OUT_DIR, 'purchased_packages.json'), '[]');
  }

  let paymentsCount = 0;
  try {
    paymentsCount = await exportCollection('payments', 'payment_bookings.json');
  } catch (e1) {
    console.warn('payments collection failed:', e1.message);
  }
  if (paymentsCount === 0) {
    try {
      await exportCollection('paymentBookings', 'payment_bookings.json');
    } catch (e2) {
      try {
        await exportCollection('bookings', 'payment_bookings.json');
      } catch (e3) {
        fs.writeFileSync(path.join(OUT_DIR, 'payment_bookings.json'), '[]');
        console.log('Wrote empty payment_bookings.json');
      }
    }
  }

  console.log('Done. Run: php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json --payment-bookings=storage/app/payment_bookings.json');
  process.exit(0);
})();
