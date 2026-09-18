import * as Models from '@definitions/consoleArchive/models';
import { FractalResponseData } from '@/api/http';

export default class Transformers {
    static toConsoleArchiveEntry = ({ attributes }: FractalResponseData): Models.ConsoleArchiveEntry => {
        return {
            id: attributes.id,
            loggedAt: new Date(attributes.logged_at),
            line: attributes.line,
            source: attributes.source,
            player: attributes.player,
        };
    };
}
