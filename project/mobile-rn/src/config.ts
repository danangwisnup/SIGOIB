// Konfigurasi API. Override saat build/run:
//   npx react-native run-android -- (edit src/config.ts atau gunakan .env CI)
// Untuk produksi, ganti defaultValue atau build dengan API_BASE_URL milik Anda.
import Config from './buildConfig';

export const API_BASE_URL: string = Config.API_BASE_URL;
export const APP_VERSION = '1.0.0';
