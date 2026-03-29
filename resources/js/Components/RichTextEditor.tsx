import React, { useEffect } from 'react';
import { useEditor, EditorContent, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import { cn } from '@/lib/utils';
import {
    BoldIcon,
    ItalicIcon,
    UnderlineIcon,
    ListIcon,
    ListOrderedIcon,
    LinkIcon,
    AlignLeftIcon,
    AlignCenterIcon,
    QuoteIcon,
    Undo2Icon,
    Redo2Icon,
} from 'lucide-react';

export interface RichTextEditorHandle {
    insertContent: (html: string) => void;
    clearContent: () => void;
}

interface Props {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    className?: string;
    minHeight?: string;
    onEditorReady?: (editor: Editor) => void;
    onKeyDown?: (e: KeyboardEvent) => void;
}

function ToolbarButton({
    onClick,
    active,
    disabled,
    children,
    title,
}: {
    onClick: () => void;
    active?: boolean;
    disabled?: boolean;
    children: React.ReactNode;
    title?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={title}
            className={cn(
                'p-1.5 rounded text-sm transition-colors',
                active
                    ? 'bg-primary/10 text-primary'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                disabled && 'opacity-40 cursor-not-allowed',
            )}
        >
            {children}
        </button>
    );
}

function Toolbar({ editor }: { editor: Editor }) {
    const setLink = () => {
        const url = window.prompt('URL');
        if (!url) return;
        editor.chain().focus().setLink({ href: url }).run();
    };

    return (
        <div className="flex items-center gap-0.5 px-2 py-1.5 border-b border-border flex-wrap">
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBold().run()}
                active={editor.isActive('bold')}
                title="Bold"
            >
                <BoldIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton
                onClick={() => editor.chain().focus().toggleItalic().run()}
                active={editor.isActive('italic')}
                title="Italic"
            >
                <ItalicIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton
                onClick={() => editor.chain().focus().toggleUnderline().run()}
                active={editor.isActive('underline')}
                title="Underline"
            >
                <UnderlineIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <div className="w-px h-4 bg-border mx-1" />

            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBulletList().run()}
                active={editor.isActive('bulletList')}
                title="Bullet list"
            >
                <ListIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
                active={editor.isActive('orderedList')}
                title="Ordered list"
            >
                <ListOrderedIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
                active={editor.isActive('blockquote')}
                title="Quote"
            >
                <QuoteIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <div className="w-px h-4 bg-border mx-1" />

            <ToolbarButton onClick={setLink} active={editor.isActive('link')} title="Insert link">
                <LinkIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <div className="w-px h-4 bg-border mx-1" />

            <ToolbarButton
                onClick={() => editor.chain().focus().setTextAlign('left').run()}
                active={editor.isActive({ textAlign: 'left' })}
                title="Align left"
            >
                <AlignLeftIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton
                onClick={() => editor.chain().focus().setTextAlign('center').run()}
                active={editor.isActive({ textAlign: 'center' })}
                title="Align center"
            >
                <AlignCenterIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <div className="ml-auto flex items-center gap-0.5">
                <ToolbarButton
                    onClick={() => editor.chain().focus().undo().run()}
                    disabled={!editor.can().undo()}
                    title="Undo"
                >
                    <Undo2Icon className="h-3.5 w-3.5" />
                </ToolbarButton>
                <ToolbarButton
                    onClick={() => editor.chain().focus().redo().run()}
                    disabled={!editor.can().redo()}
                    title="Redo"
                >
                    <Redo2Icon className="h-3.5 w-3.5" />
                </ToolbarButton>
            </div>
        </div>
    );
}

export default function RichTextEditor({
    value,
    onChange,
    placeholder = 'Write your message…',
    className,
    minHeight = '120px',
    onEditorReady,
    onKeyDown,
}: Props) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            Underline,
            Link.configure({ openOnClick: false }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Placeholder.configure({ placeholder }),
        ],
        content: value,
        onUpdate: ({ editor }) => {
            onChange(editor.getHTML());
        },
    });

    useEffect(() => {
        if (editor && onEditorReady) onEditorReady(editor);
    }, [editor]);

    useEffect(() => {
        if (!editor || !onKeyDown) return;
        const dom = editor.view.dom as HTMLElement;
        dom.addEventListener('keydown', onKeyDown);
        return () => dom.removeEventListener('keydown', onKeyDown);
    }, [editor, onKeyDown]);

    if (!editor) return null;

    return (
        <div className={cn('rounded-md border border-input overflow-hidden', className)}>
            <Toolbar editor={editor} />
            <EditorContent
                editor={editor}
                className="prose prose-sm max-w-none px-3 py-2 focus-within:outline-none"
                style={{ minHeight }}
            />
        </div>
    );
}
