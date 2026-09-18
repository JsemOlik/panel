// Shared shape for "send a power signal to a group of servers" actions — used by both the
// account-wide bulk power action (all servers a user owns) and the area power action (the
// members of a single area). Keeping one type here means the result-summary UI only needs to
// be written once.
export type PowerSignal = 'start' | 'stop' | 'restart' | 'kill';

export interface PowerActionResult<S extends PowerSignal = PowerSignal> {
    signal: S;
    succeeded: string[];
    skipped: string[];
    failed: string[];
}
