#!/bin/bash
# Smoke test Phase 7 - Sistem Monitoring IB & Quick Check
export TZ=Asia/Jakarta   # samakan dengan APP_TIMEZONE backend
B=http://127.0.0.1:8899/api
PASS=0; FAIL=0
ck(){ if [ "$2" = "$3" ]; then echo "PASS: $1"; PASS=$((PASS+1)); else echo "FAIL: $1 -> expected[$3] got[$2]"; FAIL=$((FAIL+1)); fi }
jg(){ echo "$1" | python3 -c "import sys,json;d=json.load(sys.stdin);print(eval(sys.argv[1]))" "$2" 2>/dev/null; }

echo "===== 1. AUTH ====="
R=$(curl -s -X POST $B/auth/login -H "Content-Type: application/json" -d '{"username":"admin","password":"admin123"}')
T=$(jg "$R" "d['data']['token']"); ck "admin login" "$(jg "$R" "d['success']")" "True"
R=$(curl -s -X POST $B/auth/login -H "Content-Type: application/json" -d '{"username":"admin","password":"salah"}')
ck "login salah -> 401 msg" "$(jg "$R" "d['success']")" "False"
R=$(curl -s -o /dev/null -w "%{http_code}" $B/personnel)
ck "tanpa token -> 401" "$R" "401"
R=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer tokenpalsu123" $B/dashboard)
ck "token invalid -> 401" "$R" "401"

echo "===== 2. PERSONNEL + IMPORT ====="
R=$(curl -s -H "Authorization: Bearer $T" "$B/personnel?per_page=20")
ck "list personnel (8)" "$(jg "$R" "d['data']['total']")" "8"
printf 'NRP,Nama,Pangkat,Jabatan,Kompi,Peleton,Foto\n320009,Test Import A,Serka,Ba,Kompi A,Peleton A1,\n320010,Test Import B,Kopda,Tamtama,Kompi B,Peleton B2,\n320001,Duplicate NRP,Serka,Ba,Kompi A,Peleton A1,\n320011,Salah Kompi,Kopda,Tamtama,Kompi Z,Peleton B2,\n' > /tmp/import_test.csv
R=$(curl -s -H "Authorization: Bearer $T" -F "file=@/tmp/import_test.csv" -F "mode=preview" $B/personnel/import)
ck "import preview valid=2" "$(jg "$R" "d['data']['valid']")" "2"
ck "import preview invalid=2" "$(jg "$R" "d['data']['invalid']")" "2"
R=$(curl -s -H "Authorization: Bearer $T" -F "file=@/tmp/import_test.csv" -F "mode=commit" $B/personnel/import)
ck "import commit imported=2" "$(jg "$R" "d['data']['imported']")" "2"
R=$(curl -s -H "Authorization: Bearer $T" "$B/personnel?per_page=50")
ck "total personnel setelah import (10)" "$(jg "$R" "d['data']['total']")" "10"

echo "===== 3. DEVICE LIFECYCLE ====="
R=$(curl -s -X POST $B/device/register -H "Content-Type: application/json" -d '{"nrp":"320002","device_uuid":"uuid-A-320002","platform":"android","model":"TestA","app_version":"1.0.0"}')
ck "register A -> PENDING" "$(jg "$R" "d['data']['device_status']")" "PENDING"
R=$(curl -s -X POST $B/device/register -H "Content-Type: application/json" -d '{"nrp":"320002","device_uuid":"uuid-B-320002","platform":"android","model":"TestB","app_version":"1.0.0"}')
ck "register B -> PENDING (kedua boleh pending)" "$(jg "$R" "d['data']['device_status']")" "PENDING"
R=$(curl -s -X POST $B/device/register -H "Content-Type: application/json" -d '{"nrp":"999999","device_uuid":"uuid-X","platform":"android"}')
ck "NRP tidak dikenal -> error" "$(jg "$R" "d['success']")" "False"
R=$(curl -s -H "Authorization: Bearer $T" $B/devices/pending)
IDA=$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin)['data']['items'];print([x['id'] for x in d if x['device_uuid']=='uuid-A-320002'][0])")
IDB=$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin)['data']['items'];print([x['id'] for x in d if x['device_uuid']=='uuid-B-320002'][0])")
R=$(curl -s -X POST -H "Authorization: Bearer $T" $B/devices/$IDA/approve)
DT=$(jg "$R" "d['data']['device_token']"); ck "approve A -> ACTIVE+token" "$(jg "$R" "d['success']")" "True"
R=$(curl -s -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $T" $B/devices/$IDB/approve)
ck "approve B -> 409 (satu ACTIVE saja)" "$R" "409"
R=$(curl -s "$B/device/status?device_uuid=uuid-A-320002")
ck "status by uuid -> ACTIVE + token" "$(jg "$R" "d['data']['device_status']")" "ACTIVE"
ck "uuid status mengembalikan device_token" "$([ -n "$(jg "$R" "d['data'].get('device_token')")" ] && echo yes)" "yes"
R=$(curl -s -H "Authorization: Bearer $DT" "$B/device/status?battery=82")
ck "status by token -> tracking false (belum ada session)" "$(jg "$R" "d['data']['tracking_required']")" "False"

echo "===== 4. IB LIFECYCLE ====="
NOW=$(date -d '-1 min' '+%Y-%m-%d %H:%M:%S'); END=$(date -d '+2 hour' '+%Y-%m-%d %H:%M:%S')
FUT=$(date -d '+1 hour' '+%Y-%m-%d %H:%M:%S'); FUTE=$(date -d '+3 hour' '+%Y-%m-%d %H:%M:%S')
R=$(curl -s -X POST -H "Authorization: Bearer $T" -H "Content-Type: application/json" -d "{\"name\":\"IB Test\",\"start_at\":\"$NOW\",\"end_at\":\"$END\",\"target_type\":\"SEMUA\"}" $B/monitoring/ib)
IBID=$(jg "$R" "d['data']['id']"); ck "buat IB (mulai sekarang)" "$(jg "$R" "d['success']")" "True"
R=$(curl -s -H "Authorization: Bearer $T" $B/monitoring/$IBID)
ck "IB langsung ACTIVE (server time)" "$(jg "$R" "d['data']['session']['status']")" "ACTIVE"
R=$(curl -s -X POST -H "Authorization: Bearer $T" -H "Content-Type: application/json" -d "{\"name\":\"IB Future\",\"start_at\":\"$FUT\",\"end_at\":\"$FUTE\",\"target_type\":\"SEMUA\"}" $B/monitoring/ib)
FUTID=$(jg "$R" "d['data']['id']")
R=$(curl -s -H "Authorization: Bearer $T" $B/monitoring/$FUTID)
ck "IB masa depan -> SCHEDULED" "$(jg "$R" "d['data']['session']['status']")" "SCHEDULED"
R=$(curl -s -H "Authorization: Bearer $DT" $B/device/status)
ck "tracking_required=true saat IB ACTIVE" "$(jg "$R" "d['data']['tracking_required']")" "True"
mysql monitoring_ib -e "UPDATE monitoring_sessions SET start_at=NOW()-INTERVAL 1 MINUTE WHERE id=$FUTID" 2>/dev/null
R=$(curl -s -H "Authorization: Bearer $T" $B/monitoring/$FUTID)
ck "SCHEDULED->ACTIVE otomatis saat start tercapai" "$(jg "$R" "d['data']['session']['status']")" "ACTIVE"
mysql monitoring_ib -e "UPDATE monitoring_sessions SET end_at=NOW()-INTERVAL 1 MINUTE WHERE id=$FUTID" 2>/dev/null
R=$(curl -s -H "Authorization: Bearer $T" $B/monitoring/$FUTID)
ck "ACTIVE->COMPLETED otomatis saat end tercapai" "$(jg "$R" "d['data']['session']['status']")" "COMPLETED"

echo "===== 5. LOCATION SYNC + IDEMPOTENCY ====="
cat > /tmp/pts.json <<'EOF'
{"points":[
 {"client_point_id":"cp-1","latitude":-6.2000,"longitude":106.8500,"accuracy":10,"altitude":20,"speed":0,"battery":80,"recorded_at":"REC1"},
 {"client_point_id":"cp-2","latitude":-6.2001,"longitude":106.8501,"accuracy":10,"battery":79,"recorded_at":"REC2"},
 {"client_point_id":"cp-3","latitude":-6.2002,"longitude":106.8502,"accuracy":10,"battery":79,"recorded_at":"REC3"},
 {"client_point_id":"cp-4","latitude":-6.2003,"longitude":106.8503,"accuracy":10,"battery":78,"recorded_at":"REC4"},
 {"client_point_id":"cp-5","latitude":-6.2004,"longitude":106.8504,"accuracy":10,"battery":78,"recorded_at":"REC5"},
 {"client_point_id":"cp-bad","latitude":999,"longitude":106.8505,"recorded_at":"REC6"}
]}
EOF
T1=$(date -d '-5 min' '+%Y-%m-%d %H:%M:%S'); T2=$(date -d '-4 min' '+%Y-%m-%d %H:%M:%S'); T3=$(date -d '-3 min' '+%Y-%m-%d %H:%M:%S')
T4=$(date -d '-2 min' '+%Y-%m-%d %H:%M:%S'); T5=$(date -d '-1 min' '+%Y-%m-%d %H:%M:%S')
sed -i "s/REC1/$T1/;s/REC2/$T2/;s/REC3/$T3/;s/REC4/$T4/;s/REC5/$T5/;s/REC6/$T5/" /tmp/pts.json
R=$(curl -s -X POST -H "Authorization: Bearer $DT" -H "Content-Type: application/json" -d @/tmp/pts.json $B/location/sync)
ck "sync inserted=5" "$(jg "$R" "d['data']['inserted']")" "5"
ck "sync failed=1 (lat invalid)" "$(jg "$R" "d['data']['failed']")" "1"
R=$(curl -s -X POST -H "Authorization: Bearer $DT" -H "Content-Type: application/json" -d @/tmp/pts.json $B/location/sync)
INS=$(jg "$R" "d['data']['inserted']"); DUP=$(jg "$R" "d['data']['duplicated']")
ck "retry batch sama -> duplicated=5, inserted=0" "$INS-$DUP" "0-5"
R=$(curl -s -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $DT" -H "Content-Type: application/json" -d '{"points":[{"latitude":1,"longitude":1,"recorded_at":"'$T5'","personnel_id":1}]}' $B/location/sync)
CNT=$(mysql monitoring_ib -N -e "SELECT COUNT(*) FROM locations l JOIN devices d ON d.id=l.device_id WHERE d.personnel_id=1" 2>/dev/null)
ck "personnel_id dari mobile diabaikan (personel 1 tetap 0 lokasi)" "$CNT" "0"

echo "===== 6. GEOFENCE ALERT ====="
GIN="{\"points\":[{\"client_point_id\":\"cp-g1\",\"latitude\":-6.1753924,\"longitude\":106.8271528,\"battery\":77,\"recorded_at\":\"$(date '+%Y-%m-%d %H:%M:%S')\"}]}"
R=$(curl -s -X POST -H "Authorization: Bearer $DT" -H "Content-Type: application/json" -d "$GIN" $B/location/sync)
R=$(curl -s -H "Authorization: Bearer $T" "$B/alerts?per_page=50")
ck "ENTER alert dibuat" "$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin)['data']['items'];print(len([a for a in d if a['type']=='ENTER']))")" "1"
GIN2="{\"points\":[{\"client_point_id\":\"cp-g2\",\"latitude\":-6.1754,\"longitude\":106.8272,\"battery\":76,\"recorded_at\":\"$(date '+%Y-%m-%d %H:%M:%S')\"}]}"
curl -s -X POST -H "Authorization: Bearer $DT" -H "Content-Type: application/json" -d "$GIN2" $B/location/sync > /dev/null
R=$(curl -s -H "Authorization: Bearer $T" "$B/alerts?per_page=50")
ck "INSIDE alert dibuat (1x)" "$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin)['data']['items'];print(len([a for a in d if a['type']=='INSIDE']))")" "1"
GIN3="{\"points\":[{\"client_point_id\":\"cp-g3\",\"latitude\":-6.1755,\"longitude\":106.8273,\"battery\":75,\"recorded_at\":\"$(date '+%Y-%m-%d %H:%M:%S')\"}]}"
curl -s -X POST -H "Authorization: Bearer $DT" -H "Content-Type: application/json" -d "$GIN3" $B/location/sync > /dev/null
R=$(curl -s -H "Authorization: Bearer $T" "$B/alerts?per_page=50")
ck "INSIDE tidak spam (tetap 1)" "$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin)['data']['items'];print(len([a for a in d if a['type']=='INSIDE']))")" "1"
GOUT="{\"points\":[{\"client_point_id\":\"cp-g4\",\"latitude\":-6.3000,\"longitude\":106.9000,\"battery\":74,\"recorded_at\":\"$(date '+%Y-%m-%d %H:%M:%S')\"}]}"
curl -s -X POST -H "Authorization: Bearer $DT" -H "Content-Type: application/json" -d "$GOUT" $B/location/sync > /dev/null
R=$(curl -s -H "Authorization: Bearer $T" "$B/alerts?per_page=50")
ck "EXIT alert dibuat" "$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin)['data']['items'];print(len([a for a in d if a['type']=='EXIT']))")" "1"
LSES=$(mysql monitoring_ib -N -e "SELECT COUNT(DISTINCT location_id) FROM location_sessions WHERE session_id=$IBID" 2>/dev/null)
ck "location_sessions terisi (link ke IB)" "$([ "$LSES" -ge 8 ] && echo ok)" "ok"

echo "===== 7. QUICK CHECK + OVERLAP ====="
R=$(curl -s -X POST -H "Authorization: Bearer $T" -H "Content-Type: application/json" -d '{"duration_minutes":30,"target_type":"SEMUA","name":"QC Test"}' $B/monitoring/quick-check)
QCID=$(jg "$R" "d['data']['id']"); ck "quick check 30 menit dibuat" "$(jg "$R" "d['success']")" "True"
R=$(curl -s -H "Authorization: Bearer $DT" $B/device/status)
ck "overlap: active_sessions=2" "$(jg "$R" "len(d['data']['active_sessions'])")" "2"
ck "overlap: tracking_required=true" "$(jg "$R" "d['data']['tracking_required']")" "True"
mysql monitoring_ib -e "UPDATE monitoring_sessions SET end_at=NOW()-INTERVAL 1 MINUTE WHERE id=$QCID" 2>/dev/null
R=$(curl -s -H "Authorization: Bearer $DT" $B/device/status)
ck "QC selesai + IB aktif -> tetap ON" "$(jg "$R" "d['data']['tracking_required']")" "True"
mysql monitoring_ib -e "UPDATE monitoring_sessions SET end_at=NOW()-INTERVAL 1 MINUTE WHERE id=$IBID" 2>/dev/null
R=$(curl -s -H "Authorization: Bearer $DT" $B/device/status)
ck "semua selesai -> STANDBY (tracking false)" "$(jg "$R" "d['data']['tracking_required']")" "False"
ck "server_time ada" "$([ -n "$(jg "$R" "d['data']['server_time']")" ] && echo yes)" "yes"
ck "personnel ada di payload" "$(jg "$R" "d['data']['personnel']['nrp']")" "320002"

echo "===== 8. DASHBOARD / HISTORY / REPORT ====="
R=$(curl -s -H "Authorization: Bearer $T" $B/dashboard)
ck "dashboard total_personnel=10" "$(jg "$R" "d['data']['total_personnel']")" "10"
R=$(curl -s -H "Authorization: Bearer $T" $B/dashboard/locations)
ck "dashboard markers endpoint OK" "$(jg "$R" "d['success']")" "True"
PID=$(mysql monitoring_ib -N -e "SELECT id FROM personnel WHERE nrp='320002'" 2>/dev/null)
R=$(curl -s -H "Authorization: Bearer $T" "$B/history/personnel/$PID")
ck "history: points>0" "$([ "$(jg "$R" "d['data']['total_points']")" -gt 0 ] && echo ok)" "ok"
ck "history: alerts>0" "$([ "$(jg "$R" "len(d['data']['alerts'])")" -gt 0 ] && echo ok)" "ok"
R=$(curl -s -H "Authorization: Bearer $T" $B/reports/monitoring/$IBID)
ck "report JSON rows>0" "$([ "$(jg "$R" "len(d['data']['rows'])")" -gt 0 ] && echo ok)" "ok"
R=$(curl -s -H "Authorization: Bearer $T" "$B/reports/monitoring/$IBID?format=csv")
ck "report CSV berisi header NRP" "$(echo "$R" | grep -c 'NRP')" "1"

echo "===== 9. ROLE AUTHORIZATION (BACKEND) ====="
RD=$(curl -s -X POST $B/auth/login -H "Content-Type: application/json" -d '{"username":"danki.a","password":"danki123"}')
TD=$(jg "$RD" "d['data']['token']")
R=$(curl -s -H "Authorization: Bearer $TD" "$B/personnel?per_page=50")
ck "DANKI hanya lihat Kompi A (6)" "$(jg "$R" "d['data']['total']")" "6"
RT=$(curl -s -X POST $B/auth/login -H "Content-Type: application/json" -d '{"username":"danton.a1","password":"danton123"}')
TT=$(jg "$RT" "d['data']['token']")
R=$(curl -s -H "Authorization: Bearer $TT" "$B/personnel?per_page=50")
ck "DANTON hanya lihat Peleton A1 (4)" "$(jg "$R" "d['data']['total']")" "4"
PB=$(mysql monitoring_ib -N -e "SELECT id FROM personnel WHERE nrp='320006'" 2>/dev/null)
R=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TD" $B/personnel/$PB)
ck "DANKI akses personel Kompi B -> 404" "$R" "404"
R=$(curl -s -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $TD" -H "Content-Type: application/json" -d "{\"name\":\"X\",\"start_at\":\"$NOW\",\"end_at\":\"$END\",\"target_type\":\"SEMUA\"}" $B/monitoring/ib)
ck "DANKI buat IB -> 403" "$R" "403"
R=$(curl -s -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $TD" $B/devices/$IDB/reject)
ck "DANKI reject device -> 403" "$R" "403"
R=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TD" $B/users)
ck "DANKI list users -> 403" "$R" "403"
R=$(curl -s -H "Authorization: Bearer $TD" $B/devices)
ck "DANKI devices scoped (hanya Kompi A)" "$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin)['data']['items'];print(len(set(x['company_id'] for x in d))<=1)")" "True"

echo "===== 10. REVOKE + REPLACEMENT ====="
R=$(curl -s -X POST -H "Authorization: Bearer $T" $B/devices/$IDA/revoke)
ck "revoke device" "$(jg "$R" "d['success']")" "True"
R=$(curl -s -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $DT" -H "Content-Type: application/json" -d @/tmp/pts.json $B/location/sync)
ck "revoked device sync -> 403" "$R" "403"
R=$(curl -s -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $DT" -H "Content-Type: application/json" -d '{"event_type":"APP_STARTED"}' $B/device/event)
ck "revoked device event -> 403" "$R" "403"
R=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $DT" $B/device/status)
ck "revoked device status -> 403" "$R" "403"
R=$(curl -s "$B/device/status?device_uuid=uuid-A-320002")
ck "status by uuid -> REVOKED msg" "$(jg "$R" "d['data']['device_status']")" "REVOKED"
R=$(curl -s -X POST $B/device/register -H "Content-Type: application/json" -d '{"nrp":"320002","device_uuid":"uuid-C-320002","platform":"android","model":"TestC"}')
ck "replacement: register HP baru -> PENDING" "$(jg "$R" "d['data']['device_status']")" "PENDING"
R=$(curl -s -H "Authorization: Bearer $T" $B/devices/pending)
IDC=$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin)['data']['items'];print([x['id'] for x in d if x['device_uuid']=='uuid-C-320002'][0])")
R=$(curl -s -X POST -H "Authorization: Bearer $T" $B/devices/$IDC/approve)
ck "replacement: approve -> ACTIVE" "$(jg "$R" "d['success']")" "True"
DTC=$(jg "$R" "d['data']['device_token']")
R=$(curl -s -H "Authorization: Bearer $DTC" $B/device/status)
ck "replacement: device baru berfungsi" "$(jg "$R" "d['data']['device_status']")" "ACTIVE"
R=$(curl -s -X POST $B/device/register -H "Content-Type: application/json" -d '{"nrp":"320002","device_uuid":"uuid-D-320002","platform":"android"}')
ck "NRP sudah punya ACTIVE -> ditolak" "$(jg "$R" "d['message']")" "NRP ini sudah terdaftar pada perangkat lain. Jika Anda mengganti perangkat, hubungi admin."

echo "===== 11. AUDIT LOG ====="
R=$(curl -s -H "Authorization: Bearer $T" $B/audit-logs)
for A in approve_device revoke_device create_monitoring import_personnel login; do
  ck "audit berisi $A" "$(echo "$R" | grep -c $A | python3 -c "import sys;print('yes' if int(sys.stdin.read())>0 else 'no')")" "yes"
done

echo ""
echo "=========================================="
echo "HASIL: PASS=$PASS FAIL=$FAIL"
echo "=========================================="
