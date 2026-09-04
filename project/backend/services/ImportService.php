<?php
// Import personel dari CSV (Excel: simpan sebagai CSV lalu upload).
// Kolom: NRP, Nama, Pangkat, Jabatan, Kompi, Peleton, Foto(optional)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../models/Organization.php';

class ImportService
{
    // Parse + validasi. Return ['rows' => [ ['row'=>n, 'data'=>[...], 'errors'=>[...]] ]]
    public static function parseAndValidate(string $tmpPath): array
    {
        $fh = fopen($tmpPath, 'r');
        if (!$fh) {
            return ['rows' => [], 'fatal' => 'File tidak dapat dibaca.'];
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return ['rows' => [], 'fatal' => 'File CSV kosong.'];
        }
        // Normalisasi header (case-insensitive)
        $cols = array_map(fn($h) => strtolower(trim((string)$h)), $header);
        $idx = function (array $names) use ($cols) {
            foreach ($names as $n) {
                $i = array_search($n, $cols, true);
                if ($i !== false) {
                    return $i;
                }
            }
            return null;
        };
        $iNrp = $idx(['nrp']);
        $iName = $idx(['nama', 'name']);
        $iRank = $idx(['pangkat', 'rank']);
        $iPos = $idx(['jabatan', 'position']);
        $iComp = $idx(['kompi', 'company']);
        $iPlat = $idx(['peleton', 'platoon']);
        $iPhoto = $idx(['foto', 'photo']);

        if ($iNrp === null || $iName === null) {
            fclose($fh);
            return ['rows' => [], 'fatal' => 'Header wajib minimal berisi kolom: NRP, Nama.'];
        }

        $rows = [];
        $seenNrps = [];
        $lineNrps = [];
        $lineNo = 1;
        while (($r = fgetcsv($fh)) !== false) {
            $lineNo++;
            if (count(array_filter($r, fn($v) => trim((string)$v) !== '')) === 0) {
                continue; // baris kosong
            }
            $data = [
                'nrp' => trim((string)($r[$iNrp] ?? '')),
                'name' => trim((string)($r[$iName] ?? '')),
                'rank' => $iRank !== null ? trim((string)($r[$iRank] ?? '')) : null,
                'position' => $iPos !== null ? trim((string)($r[$iPos] ?? '')) : null,
                'company_name' => $iComp !== null ? trim((string)($r[$iComp] ?? '')) : null,
                'platoon_name' => $iPlat !== null ? trim((string)($r[$iPlat] ?? '')) : null,
                'photo' => $iPhoto !== null ? trim((string)($r[$iPhoto] ?? '')) : null,
            ];
            $rows[] = ['row' => $lineNo, 'data' => $data, 'errors' => []];
            if ($data['nrp'] !== '') {
                $lineNrps[] = $data['nrp'];
            }
        }
        fclose($fh);

        // Cek NRP yang sudah ada di database (1 query)
        $existing = array_flip(Personnel::existingNrps(array_unique($lineNrps)));

        foreach ($rows as &$row) {
            $d = $row['data'];
            if ($d['nrp'] === '') {
                $row['errors'][] = 'NRP wajib diisi.';
            } else {
                if (isset($seenNrps[$d['nrp']])) {
                    $row['errors'][] = 'NRP duplicate di dalam file (baris ' . $seenNrps[$d['nrp']] . ').';
                }
                if (isset($existing[$d['nrp']])) {
                    $row['errors'][] = 'NRP sudah terdaftar di database.';
                }
                $seenNrps[$d['nrp']] = $row['row'];
            }
            if ($d['name'] === '') {
                $row['errors'][] = 'Nama wajib diisi.';
            }

            $company = null;
            if ($d['company_name']) {
                $company = Organization::findByNameType($d['company_name'], 'KOMPI');
                if (!$company) {
                    $row['errors'][] = 'Kompi "' . $d['company_name'] . '" tidak valid.';
                }
            }
            $platoon = null;
            if ($d['platoon_name']) {
                $platoon = Organization::findByNameType($d['platoon_name'], 'PELETON');
                if (!$platoon) {
                    $row['errors'][] = 'Peleton "' . $d['platoon_name'] . '" tidak valid.';
                } elseif ($company && (int)$platoon['parent_id'] !== (int)$company['id']) {
                    $row['errors'][] = 'Peleton "' . $d['platoon_name'] . '" bukan bagian dari Kompi "' . $d['company_name'] . '".';
                }
            }
            $row['data']['company_id'] = $company ? (int)$company['id'] : null;
            $row['data']['platoon_id'] = $platoon ? (int)$platoon['id'] : null;
        }
        unset($row);

        return ['rows' => $rows];
    }

    // Simpan hanya baris tanpa error.
    public static function commit(array $rows): array
    {
        $pdo = Database::pdo();
        $imported = 0;
        $skipped = 0;
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                if ($row['errors']) {
                    $skipped++;
                    continue;
                }
                Personnel::create($row['data']);
                $imported++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
