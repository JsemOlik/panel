import { buildCommand } from '@/lib/consoleShortcuts';

describe('@/lib/consoleShortcuts.ts', function () {
    describe('buildCommand()', function () {
        it('should return a command without placeholders unchanged', function () {
            expect(buildCommand('save-all', {})).toBe('save-all');
        });

        it('should fill in the argument values', function () {
            expect(buildCommand('kick {{player}} {{reason}}', { player: 'Steve', reason: 'Bye' })).toBe(
                'kick Steve Bye'
            );
        });

        it('should allow whitespace inside the placeholder', function () {
            expect(buildCommand('say {{ message }}', { message: 'hello' })).toBe('say hello');
        });

        it('should replace every use of the same argument', function () {
            expect(buildCommand('give {{player}} to {{player}}', { player: 'Steve' })).toBe('give Steve to Steve');
        });

        it('should leave out values that are missing or empty', function () {
            expect(buildCommand('kick {{player}} {{reason}}', { player: 'Steve' })).toBe('kick Steve ');
            expect(buildCommand('kick {{player}}', { player: '' })).toBe('kick ');
        });

        it('should ignore placeholders that are not valid keys', function () {
            expect(buildCommand('say {{ hello world }}', { 'hello world': 'nope' })).toBe('say {{ hello world }}');
        });

        // A value with a line break would otherwise be sent as a second console command.
        it('should replace line breaks in the values with a space', function () {
            expect(buildCommand('say {{message}}', { message: 'hello\nstop' })).toBe('say hello stop');
            expect(buildCommand('say {{message}}', { message: 'hello\r\n\r\nstop' })).toBe('say hello stop');
        });
    });
});
