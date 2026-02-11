/**
 * Export Firestore collections to JSON for Laravel firestore:migrate.
 * Project: flexana-test
 *
 * Structure: payments (top-level), users (top-level), packages (top-level),
 *            purchasedPackages (top-level OR under users/{userId}/purchasedPackages)
 *
 * 1. serviceAccountKey.json in this folder
 * 2. npm install && npm run export
 * 3. php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json --payment-bookings=storage/app/payment_bookings.json
 */

const admin = require('firebase-admin');
const fs = require('fs');
const path = require('path');

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

async function exportCollection(collectionName, outFile) {
  const snapshot = await db.collection(collectionName).get();
  const docs = snapshot.docs.map(docToPlain);
  const outPath = path.join(OUT_DIR, outFile);
  fs.writeFileSync(outPath, JSON.stringify(docs, null, 2));
  console.log(`Exported ${docs.length} doc(s) from "${collectionName}" → ${outPath}`);
  return docs.length;
}

/** Export all purchasedPackages subcollection docs (e.g. users/{uid}/purchasedPackages) via collection group. */
async function exportPurchasedPackagesCollectionGroup(outFile) {
  const snapshot = await db.collectionGroup('purchasedPackages').get();
  const all = [];
  for (const doc of snapshot.docs) {
    const row = docToPlain(doc);
    const parentId = doc.ref.parent.parent?.id;
    if (parentId && !row.uid) row.uid = parentId;
    all.push(row);
  }
  const outPath = path.join(OUT_DIR, outFile);
  fs.writeFileSync(outPath, JSON.stringify(all, null, 2));
  console.log(`Exported ${all.length} doc(s) from collection group "purchasedPackages" → ${outPath}`);
  return all.length;
}

(async () => {
  let purchasedCount = 0;
  try {
    purchasedCount = await exportCollection('purchasedPackages', 'purchased_packages.json');
  } catch (e) {
    console.warn('Top-level purchasedPackages failed:', e.message);
  }
  if (purchasedCount === 0) {
    try {
      purchasedCount = await exportCollection('purchased_packages', 'purchased_packages.json');
    } catch (e) {
      // ignore
    }
  }
  if (purchasedCount === 0) {
    try {
      await exportPurchasedPackagesCollectionGroup('purchased_packages.json');
    } catch (e) {
      console.warn('Collection group purchasedPackages failed:', e.message);
      fs.writeFileSync(path.join(OUT_DIR, 'purchased_packages.json'), '[]');
    }
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
