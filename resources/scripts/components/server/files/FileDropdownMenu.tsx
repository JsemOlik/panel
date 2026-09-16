import React, { memo, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBoxOpen,
    faCopy,
    faEllipsisH,
    faFileArchive,
    faFileCode,
    faFileDownload,
    faLevelUpAlt,
    faPencilAlt,
    faTrashAlt,
    IconDefinition,
} from '@fortawesome/free-solid-svg-icons';
import RenameFileModal from '@/components/server/files/RenameFileModal';
import { ServerContext } from '@/state/server';
import { join } from 'pathe';
import deleteFiles from '@/api/server/files/deleteFiles';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import copyFile from '@/api/server/files/copyFile';
import Can from '@/components/elements/Can';
import getFileDownloadUrl from '@/api/server/files/getFileDownloadUrl';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import { FileObject } from '@/api/server/files/loadDirectory';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import useEventListener from '@/plugins/useEventListener';
import compressFiles from '@/api/server/files/compressFiles';
import decompressFiles from '@/api/server/files/decompressFiles';
import isEqual from 'react-fast-compare';
import ChmodFileModal from '@/components/server/files/ChmodFileModal';
import { Dialog } from '@/components/elements/dialog';

type ModalType = 'rename' | 'move' | 'chmod';

interface RowProps {
    icon: IconDefinition;
    title: string;
    danger?: boolean;
    onSelect: () => void;
}

const Row = ({ icon, title, danger, onSelect }: RowProps) => (
    <DropdownMenuItem variant={danger ? 'destructive' : 'default'} onSelect={onSelect}>
        <FontAwesomeIcon icon={icon} css={tw`text-xs`} fixedWidth />
        <span>{title}</span>
    </DropdownMenuItem>
);

// Where the menu is anchored, relative to the wrapper around the toggle button. Menus opened with the
// toggle button hang below it, menus opened with a right click on the file row open at the cursor.
interface MenuAnchor {
    x: number;
    y: number;
    align: 'start' | 'end';
    fromButton: boolean;
}

const FileDropdownMenu = ({ file }: { file: FileObject }) => {
    const wrapperRef = useRef<HTMLDivElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false);
    const [anchor, setAnchor] = useState<MenuAnchor>({ x: 0, y: 0, align: 'end', fromButton: true });
    const [showSpinner, setShowSpinner] = useState(false);
    const [modal, setModal] = useState<ModalType | null>(null);
    const [showConfirmation, setShowConfirmation] = useState(false);

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { mutate } = useFileManagerSwr();
    const { clearAndAddHttpError, clearFlashes } = useFlash();
    const directory = ServerContext.useStoreState((state) => state.files.directory);

    useEventListener(`pterodactyl:files:ctx:${file.key}`, (e: CustomEvent<{ x: number; y: number }>) => {
        const rect = wrapperRef.current?.getBoundingClientRect();
        if (!rect) return;

        setAnchor({ x: e.detail.x - rect.left, y: e.detail.y - rect.top, align: 'start', fromButton: false });
        setOpen(true);
    });

    const onToggleClick = () => {
        if (open) {
            setOpen(false);
            return;
        }

        const rect = wrapperRef.current?.getBoundingClientRect();
        setAnchor({ x: rect?.width || 0, y: rect?.height || 0, align: 'end', fromButton: true });
        setOpen(true);
    };

    const doDeletion = () => {
        clearFlashes('files');

        // For UI speed, immediately remove the file from the listing before calling the deletion function.
        // If the delete actually fails, we'll fetch the current directory contents again automatically.
        mutate((files) => files.filter((f) => f.key !== file.key), false);

        deleteFiles(uuid, directory, [file.name]).catch((error) => {
            mutate();
            clearAndAddHttpError({ key: 'files', error });
        });
    };

    const doCopy = () => {
        setShowSpinner(true);
        clearFlashes('files');

        copyFile(uuid, join(directory, file.name))
            .then(() => mutate())
            .catch((error) => clearAndAddHttpError({ key: 'files', error }))
            .then(() => setShowSpinner(false));
    };

    const doDownload = () => {
        setShowSpinner(true);
        clearFlashes('files');

        getFileDownloadUrl(uuid, join(directory, file.name))
            .then((url) => {
                // @ts-expect-error this is valid
                window.location = url;
            })
            .catch((error) => clearAndAddHttpError({ key: 'files', error }))
            .then(() => setShowSpinner(false));
    };

    const doArchive = () => {
        setShowSpinner(true);
        clearFlashes('files');

        compressFiles(uuid, directory, [file.name])
            .then(() => mutate())
            .catch((error) => clearAndAddHttpError({ key: 'files', error }))
            .then(() => setShowSpinner(false));
    };

    const doUnarchive = () => {
        setShowSpinner(true);
        clearFlashes('files');

        decompressFiles(uuid, directory, file.name)
            .then(() => mutate())
            .catch((error) => clearAndAddHttpError({ key: 'files', error }))
            .then(() => setShowSpinner(false));
    };

    return (
        <>
            <Dialog.Confirm
                open={showConfirmation}
                onClose={() => setShowConfirmation(false)}
                title={`Delete ${file.isFile ? 'File' : 'Directory'}`}
                confirm={'Delete'}
                onConfirmed={doDeletion}
            >
                You will not be able to recover the contents of&nbsp;
                <span className={'font-semibold text-gray-50'}>{file.name}</span> once deleted.
            </Dialog.Confirm>
            {modal ? (
                modal === 'chmod' ? (
                    <ChmodFileModal
                        visible
                        appear
                        files={[{ file: file.name, mode: file.modeBits }]}
                        onDismissed={() => setModal(null)}
                    />
                ) : (
                    <RenameFileModal
                        visible
                        appear
                        files={[file.name]}
                        useMoveTerminology={modal === 'move'}
                        onDismissed={() => setModal(null)}
                    />
                )
            ) : null}
            <SpinnerOverlay visible={showSpinner} fixed size={'large'} />
            <div ref={wrapperRef} css={tw`relative`}>
                <button
                    ref={buttonRef}
                    type={'button'}
                    aria-label={'File actions'}
                    aria-haspopup={'menu'}
                    aria-expanded={open}
                    css={tw`px-4 py-2 hover:text-white focus-visible:outline-none focus-visible:text-white`}
                    onClick={onToggleClick}
                >
                    <FontAwesomeIcon icon={faEllipsisH} />
                </button>
                <DropdownMenu open={open} onOpenChange={setOpen} modal={false}>
                    <DropdownMenuTrigger asChild>
                        <span
                            aria-hidden
                            tabIndex={-1}
                            css={tw`absolute w-0 h-0 pointer-events-none`}
                            style={{ left: anchor.x, top: anchor.y }}
                        />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        key={`${anchor.x}:${anchor.y}`}
                        align={anchor.align}
                        sideOffset={anchor.fromButton ? 4 : 0}
                        className={'w-48'}
                        onInteractOutside={(e: Event) => {
                            // Let the toggle button handle its own clicks so it closes the menu instead of reopening it.
                            if (buttonRef.current?.contains(e.target as Node)) {
                                e.preventDefault();
                            }
                        }}
                        onCloseAutoFocus={(e: Event) => {
                            e.preventDefault();
                            if (anchor.fromButton) {
                                buttonRef.current?.focus();
                            }
                        }}
                    >
                        <Can action={'file.update'}>
                            <Row onSelect={() => setModal('rename')} icon={faPencilAlt} title={'Rename'} />
                            <Row onSelect={() => setModal('move')} icon={faLevelUpAlt} title={'Move'} />
                            <Row onSelect={() => setModal('chmod')} icon={faFileCode} title={'Permissions'} />
                        </Can>
                        {file.isFile && (
                            <Can action={'file.create'}>
                                <Row onSelect={doCopy} icon={faCopy} title={'Copy'} />
                            </Can>
                        )}
                        {file.isArchiveType() ? (
                            <Can action={'file.create'}>
                                <Row onSelect={doUnarchive} icon={faBoxOpen} title={'Unarchive'} />
                            </Can>
                        ) : (
                            <Can action={'file.archive'}>
                                <Row onSelect={doArchive} icon={faFileArchive} title={'Archive'} />
                            </Can>
                        )}
                        {file.isFile && <Row onSelect={doDownload} icon={faFileDownload} title={'Download'} />}
                        <Can action={'file.delete'}>
                            <Row onSelect={() => setShowConfirmation(true)} icon={faTrashAlt} title={'Delete'} danger />
                        </Can>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </>
    );
};

export default memo(FileDropdownMenu, isEqual);
