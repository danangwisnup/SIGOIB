// Device token = kredensial sensitif -> secure storage (Keychain / Keystore).
// Semua akses dibungkus try/catch: kegagalan modul native TIDAK boleh meng-crash startup.
import * as Keychain from 'react-native-keychain';

const SERVICE = 'sigoib_device_token';

export async function saveDeviceToken(token: string): Promise<void> {
  // Kegagalan penyimpanan harus terlihat oleh pemanggil (aktivasi) agar tidak
  // menganggap perangkat aktif padahal token tak tersimpan.
  await Keychain.setGenericPassword('device', token, {service: SERVICE});
}

export async function getDeviceToken(): Promise<string | null> {
  try {
    const creds = await Keychain.getGenericPassword({service: SERVICE});
    return creds ? creds.password : null;
  } catch {
    // Keychain belum siap / error -> perlakukan sebagai belum ada token (aman).
    return null;
  }
}

export async function clearDeviceToken(): Promise<void> {
  try {
    await Keychain.resetGenericPassword({service: SERVICE});
  } catch {
    // abaikan: menghapus token yang tidak ada bukan kondisi fatal.
  }
}
