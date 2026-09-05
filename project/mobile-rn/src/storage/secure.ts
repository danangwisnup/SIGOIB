// Device token = kredensial sensitif -> secure storage (Keychain / Keystore).
import * as Keychain from 'react-native-keychain';

const SERVICE = 'sigoib_device_token';

export async function saveDeviceToken(token: string): Promise<void> {
  await Keychain.setGenericPassword('device', token, {service: SERVICE});
}

export async function getDeviceToken(): Promise<string | null> {
  const creds = await Keychain.getGenericPassword({service: SERVICE});
  return creds ? creds.password : null;
}

export async function clearDeviceToken(): Promise<void> {
  await Keychain.resetGenericPassword({service: SERVICE});
}
