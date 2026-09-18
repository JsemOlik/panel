// Replaces {{key}} placeholders with the argument values. Line breaks are stripped so a value can
// never turn into a second console command.
export const buildCommand = (command: string, values: Record<string, string>): string =>
    command.replace(/{{\s*([A-Za-z0-9_]+)\s*}}/g, (_, key: string) => (values[key] || '').replace(/[\r\n]+/g, ' '));
