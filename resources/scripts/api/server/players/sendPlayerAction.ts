import http from '@/api/http';

export type PlayerAction = 'message' | 'kick' | 'ban';

/**
 * Messages, kicks or bans a player by name.
 *
 * The panel never builds the console command itself — the server does, from the action and the
 * player's row on this server's roster. Sending the raw command from here would make every caller
 * a place where a stray newline turns one moderation action into two console commands; see
 * app/Services/Players/PlayerModerationService.php.
 */
export default (uuid: string, player: string, action: PlayerAction, text?: string): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/players/${encodeURIComponent(player)}/action`, {
            action,
            text: text || undefined,
        })
            .then(() => resolve())
            .catch(reject);
    });
};
