-- Seed akun awal TANPA perlu SSH/PHP CLI (untuk cPanel/phpMyAdmin).
-- Jalankan SETELAH schema.sql + seed.sql. Hash = password_hash() bcrypt terverifikasi.
-- WAJIB: ganti semua password default setelah login pertama (menu Pengaturan).

USE monitoring_ib;

INSERT INTO users (name, username, password_hash, role, organization_id) VALUES
  ('Administrator',      'admin',     '$2y$10$IM4P2IlssO3fZt6vGw9ws.Af/J8LkiLpR3IQ67RaVPYMfTNSl4.5G', 'ADMIN',    NULL),
  ('Komandan Batalyon',  'komandan',  '$2y$10$nK31rfJz2TbguAMolAcKgeOK5k9RX3cAw14Bt4IyBvBHuHauxijTG', 'KOMANDAN', 1),
  ('Wadan Batalyon',     'wadan',     '$2y$10$8TsR39nAo0oDo3x6B7Hqm.iYIaviOD09WQMGGLxhEMYtcmbn8D02C', 'WADAN',    1),
  ('Danki A',            'danki.a',   '$2y$10$6HG66Ju.SGpwCS9PEEMeteeAtBq/EDG95l2e0cml1MyE1Dd7jBKpG', 'DANKI',    2),
  ('Danton A1',          'danton.a1', '$2y$10$7gnO5JFB3Zgt5w53AB/du..ePeP6cg2CS/nfpDu6vjVS1izFAO59e', 'DANTON',   4)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Password default: admin123 / komandan123 / wadan123 / danki123 / danton123
