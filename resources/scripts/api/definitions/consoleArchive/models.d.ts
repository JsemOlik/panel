import { Model } from '@/api/definitions';

type ConsoleArchiveSource = 'console' | 'chat';

interface ConsoleArchiveEntry extends Model {
    id: number;
    loggedAt: Date;
    line: string;
    source: ConsoleArchiveSource;
    player: string | null;
}

export type { ConsoleArchiveSource, ConsoleArchiveEntry };
