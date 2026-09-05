// Deklarasi minimal: react-native-sqlite-storage tidak punya tipe resmi.
declare module 'react-native-sqlite-storage' {
  const SQLite: any;
  export default SQLite;
}
