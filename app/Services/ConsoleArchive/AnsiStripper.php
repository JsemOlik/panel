<?php

namespace Pterodactyl\Services\ConsoleArchive;

/**
 * Strips ANSI/VT100 escape sequences (colour codes, cursor movement, etc.) out of raw console
 * output before it is stored or classified. Wings forwards console output to the browser exactly
 * as the process wrote it, ANSI codes included (xterm.js renders them); a stored/searched archive
 * needs plain text instead, both so `MATCH ... AGAINST` isn't defeated by stray escape bytes
 * splitting up a word and so the archive is readable outside of a terminal-emulating UI.
 */
final class AnsiStripper
{
    /**
     * Matches CSI sequences (ESC [ ... final byte), OSC sequences (ESC ] ... BEL or ESC \\), and
     * bare two-byte escapes (ESC followed by a single final byte), which together cover the color
     * codes, cursor movement, and title-setting sequences seen in real Minecraft/Paper/Bungee/
     * Velocity console output.
     */
    private const PATTERN = '/'
        . '\x1b\[[0-?]*[ -\/]*[@-~]'   // CSI ... final byte, e.g. "\e[32m", "\e[1;37m"
        . '|\x1b\][^\x07\x1b]*(?:\x07|\x1b\\\\)' // OSC ... BEL or ST
        . '|\x1b[@-Z\\\\-_]'            // two-byte escapes, e.g. "\e=", "\eM"
        . '/';

    /**
     * Also drop the remaining C0 control characters (other than tab/newline/carriage-return),
     * which occasionally show up in crash output and are never meaningful in stored chat/console
     * text.
     */
    private const CONTROL_PATTERN = '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/';

    public static function strip(string $value): string
    {
        $value = preg_replace(self::PATTERN, '', $value) ?? $value;
        $value = preg_replace(self::CONTROL_PATTERN, '', $value) ?? $value;

        return $value;
    }
}
