import { Model } from '@/api/definitions';

type PlayerStatus = 'online' | 'offline';
type PlayerSessionEvent = 'join' | 'leave';

interface ServerPlayer extends Model {
    id: number;
    name: string;
    status: PlayerStatus;
    joinedAt: Date | null;
    lastSeenAt: Date | null;
}

interface ServerPlayerSession extends Model {
    id: number;
    name: string;
    event: PlayerSessionEvent;
    occurredAt: Date;
}

export type { PlayerStatus, PlayerSessionEvent, ServerPlayer, ServerPlayerSession };
