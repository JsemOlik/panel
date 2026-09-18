import * as Models from '@definitions/players/models';
import { FractalResponseData } from '@/api/http';

export default class Transformers {
    static toServerPlayer = ({ attributes }: FractalResponseData): Models.ServerPlayer => {
        return {
            id: attributes.id,
            name: attributes.name,
            status: attributes.status,
            joinedAt: attributes.joined_at ? new Date(attributes.joined_at) : null,
            lastSeenAt: attributes.last_seen_at ? new Date(attributes.last_seen_at) : null,
        };
    };

    static toServerPlayerSession = ({ attributes }: FractalResponseData): Models.ServerPlayerSession => {
        return {
            id: attributes.id,
            name: attributes.name,
            event: attributes.event,
            occurredAt: new Date(attributes.occurred_at),
        };
    };
}
