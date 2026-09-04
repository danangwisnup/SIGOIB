-- Seed struktur organisasi awal. Jalankan SETELAH schema.sql.
-- Akun user dibuat oleh database/seed.php (agar password_hash() dipakai).

USE monitoring_ib;

INSERT INTO organizations (id, parent_id, name, type) VALUES
  (1, NULL, 'Batalyon Infanteri 500', 'BATALYON'),
  (2, 1, 'Kompi A', 'KOMPI'),
  (3, 1, 'Kompi B', 'KOMPI'),
  (4, 2, 'Peleton A1', 'PELETON'),
  (5, 2, 'Peleton A2', 'PELETON'),
  (6, 3, 'Peleton B1', 'PELETON'),
  (7, 3, 'Peleton B2', 'PELETON');
