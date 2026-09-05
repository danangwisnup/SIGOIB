# Perubahan Native Android yang WAJIB (mobile-rn)

Dokumen ini berisi HANYA perubahan native minimal yang wajib diterapkan pada folder
`android/` yang SUDAH ADA di project build Anda. **Jangan** regenerate/scaffold ulang, **jangan**
overwrite seluruh project native. Terapkan potongan berikut secara manual.

Target yang dipertahankan: React Native 0.76.5, react-native-background-actions 4.0.1,
react-native-screens 4.5.0, JDK 17, Node 20.

---

## ROOT CAUSE force close setelah approval / saat reopen (native)

Di Android 14 (API 34), sebuah foreground service WAJIB mendeklarasikan `foregroundServiceType`
dan aplikasi WAJIB memegang permission tipe tsb. `react-native-background-actions` **4.0.1**
belum bisa mengirim `foregroundServiceType` dari JS (baru ada di 4.1.0). Jika manifest tidak
mendeklarasikan tipe service, panggilan `BackgroundService.start()` melempar
`MissingForegroundServiceTypeException` / `ForegroundServiceStartNotAllowedException` di level
native — **tidak bisa** ditangkap `try/catch` JS → aplikasi force close.

Perbaikan di sisi JS (sudah dilakukan) memastikan FGS **hanya** distart saat `tracking_required=true`
dan app di foreground, sehingga tepat setelah approval (belum ada sesi aktif) FGS tidak distart.
Namun begitu ada sesi IB/Quick Check aktif, FGS tetap perlu distart, sehingga perbaikan manifest
berikut tetap WAJIB agar tidak crash saat tracking benar-benar dimulai.

---

## 1. `android/app/src/main/AndroidManifest.xml`

Tambahkan permission berikut di dalam `<manifest>` (sebelum `<application>`):

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
<uses-permission android:name="android.permission.WAKE_LOCK" />
```

Tambahkan atribut `xmlns:tools` pada tag `<manifest>` bila belum ada:

```xml
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
          xmlns:tools="http://schemas.android.com/tools">
```

Di dalam `<application> ... </application>`, override service milik library agar memiliki
`foregroundServiceType="location"` (manifest merger akan menggabungkan atribut ini ke service
yang dideklarasikan library):

```xml
<service
    android:name="com.asterinet.react.bgactions.RNBackgroundActionsTask"
    android:foregroundServiceType="location"
    android:exported="false"
    tools:node="merge" />
```

> Jika manifest merger memprotes atribut yang sudah ada, gunakan
> `tools:replace="android:foregroundServiceType"` pada elemen `<service>` di atas.

---

## 2. `android/app/build.gradle`

Pastikan minimal:

```gradle
android {
    compileSdkVersion 34   // atau 35
    defaultConfig {
        minSdkVersion 24
        targetSdkVersion 34 // Android 14
    }
}
```

(Nilai boleh mengikuti `rootProject.ext` bawaan RN 0.76.5: compileSdk 35 / target 34/35.)

---

## 3. Runtime permission (sudah ditangani di JS)

`SetupScreen` sudah meminta: lokasi (fine), background location, dan POST_NOTIFICATIONS.
Android 14 hanya mengizinkan `startForeground` tipe location bila izin lokasi granted saat
service dimulai. JS sudah menjamin FGS baru distart setelah setup permission selesai dan hanya
ketika `tracking_required=true`.

---

## 4. Nama komponen (app.json)

`app.json` sudah benar:

```json
{ "name": "sigoib", "displayName": "SIGoIB" }
```

Pastikan `MainActivity.getMainComponentName()` mengembalikan `"sigoib"` (huruf kecil), sesuai
`AppRegistry.registerComponent(appName, ...)` di `index.js`. Jangan gunakan nama lain
(mis. `SIGoIBShell`). Package Android boleh tetap seperti sekarang — tidak perlu di-rename.

---

## Batasan OS (tidak bisa dijamin)

- Bila pengguna melakukan **force-stop** dari Setelan Android, OS menghentikan semua service —
  tracking tidak bisa dijamin tetap jalan. Ini batasan OS, bukan bug.
- Memulai FGS dari background (app tertutup) dibatasi Android 12+. FGS distart saat app foreground
  (di HOME ketika sesi aktif) lalu OS mempertahankannya di background.
