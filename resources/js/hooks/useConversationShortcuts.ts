import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';

type ShortcutOpts = {
    conversationId: number;
    status: string;
    onFocusReply: () => void;
    onFocusNote: () => void;
    onClose?: () => void;
};

/**
 * Keyboard shortcuts for the conversation show page.
 *
 *   r — focus reply editor (message mode)
 *   n — focus reply editor (note mode)
 *   c — close conversation
 *   Escape — blur focused element
 */
export function useConversationShortcuts(opts: ShortcutOpts) {
    // Store latest callbacks in refs to avoid stale closures
    const optsRef = useRef(opts);
    optsRef.current = opts;

    useEffect(() => {
        function handler(e: KeyboardEvent) {
            const target = e.target as HTMLElement;

            if (e.key === 'Escape') {
                if (target.tagName !== 'BODY') (target as HTMLElement).blur?.();
                return;
            }

            // Ignore when focus is inside an input/textarea/contenteditable
            if (
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT' ||
                target.isContentEditable
            ) {
                return;
            }

            const { onFocusReply, onFocusNote, onClose, status } = optsRef.current;

            switch (e.key) {
                case 'r':
                    e.preventDefault();
                    onFocusReply();
                    break;
                case 'n':
                    e.preventDefault();
                    onFocusNote();
                    break;
                case 'c':
                    e.preventDefault();
                    if (status !== 'closed') {
                        onClose?.();
                    }
                    break;
            }
        }

        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, []); // only mount/unmount
}

type ListShortcutOpts = {
    conversationIds: number[];
    currentId: number | null;
    onSelect: (id: number) => void;
};

/**
 * Keyboard shortcuts for the conversation list (index) page.
 *
 *   j — next conversation
 *   k — previous conversation
 *   Enter / o — open selected conversation
 */
export function useConversationListShortcuts(opts: ListShortcutOpts) {
    const optsRef = useRef(opts);
    optsRef.current = opts;

    useEffect(() => {
        function handler(e: KeyboardEvent) {
            const target = e.target as HTMLElement;
            if (
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.isContentEditable
            ) {
                return;
            }

            const { conversationIds, currentId, onSelect } = optsRef.current;
            if (conversationIds.length === 0) return;

            const idx = currentId ? conversationIds.indexOf(currentId) : -1;

            switch (e.key) {
                case 'j': {
                    e.preventDefault();
                    const next = idx < conversationIds.length - 1
                        ? conversationIds[idx + 1]
                        : conversationIds[0];
                    onSelect(next);
                    break;
                }
                case 'k': {
                    e.preventDefault();
                    const prev = idx > 0
                        ? conversationIds[idx - 1]
                        : conversationIds[conversationIds.length - 1];
                    onSelect(prev);
                    break;
                }
                case 'Enter':
                case 'o': {
                    if (currentId) {
                        e.preventDefault();
                        router.visit(`/conversations/${currentId}`);
                    }
                    break;
                }
            }
        }

        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, []);
}
