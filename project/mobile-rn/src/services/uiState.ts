// State UI sederhana (in-memory pub/sub) — ditulis oleh tracking controller.
export type UiStateKind = 'IDLE' | 'STANDBY' | 'TRACKING' | 'REVOKED';

export interface UiState {
  state: UiStateKind;
  personnelName: string;
  nrp: string;
  rank: string;
  sessions: string[];
  battery: number | null;
  lastSync: string;
  gpsOk: boolean;
  netOk: boolean;
  queueCount: number;
  serverTime: string;
}

let state: UiState = {
  state: 'IDLE',
  personnelName: '',
  nrp: '',
  rank: '',
  sessions: [],
  battery: null,
  lastSync: '-',
  gpsOk: true,
  netOk: true,
  queueCount: 0,
  serverTime: '',
};

type Listener = (s: UiState) => void;
const listeners = new Set<Listener>();

export function getUiState(): UiState {
  return state;
}

export function setUiState(patch: Partial<UiState>): void {
  state = {...state, ...patch};
  listeners.forEach(l => l(state));
}

export function subscribeUiState(l: Listener): () => void {
  listeners.add(l);
  l(state);
  return () => listeners.delete(l);
}
