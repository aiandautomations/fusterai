import React, { useRef, useState } from 'react'
import { useForm } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar'
import { getInitials } from '@/lib/utils'
import { ShieldIcon, UserIcon, CameraIcon, XIcon, UploadIcon, PenLineIcon } from 'lucide-react'

interface User {
    id: number
    name: string
    email: string
    avatar?: string
    role: string
    signature?: string
}

interface Props { user: User }

function SectionCard({ title, description, icon: Icon, children }: {
    title: string
    description: string
    icon: React.ElementType
    children: React.ReactNode
}) {
    return (
        <div className="rounded-xl border border-border bg-card">
            <div className="flex items-start gap-4 px-6 py-5 border-b border-border">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted/60 text-muted-foreground">
                    <Icon className="h-4 w-4" />
                </div>
                <div>
                    <h2 className="text-[15px] font-semibold">{title}</h2>
                    <p className="text-xs text-muted-foreground mt-0.5">{description}</p>
                </div>
            </div>
            <div className="px-6 py-5">
                {children}
            </div>
        </div>
    )
}

export default function ProfileIndex({ user }: Props) {
    const profileForm = useForm({ name: user.name, email: user.email, signature: user.signature ?? '' })
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' })
    const avatarForm = useForm<{ avatar: File | null }>({ avatar: null })

    const [avatarPreview, setAvatarPreview] = useState<string | null>(user.avatar ?? null)
    const fileInputRef = useRef<HTMLInputElement>(null)

    function submitProfile(e: React.FormEvent) {
        e.preventDefault()
        profileForm.patch('/profile')
    }

    function submitPassword(e: React.FormEvent) {
        e.preventDefault()
        passwordForm.patch('/profile/password', {
            onSuccess: () => passwordForm.reset(),
        })
    }

    function handleAvatarChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0]
        if (!file) return
        avatarForm.setData('avatar', file)
        const reader = new FileReader()
        reader.onload = (ev) => setAvatarPreview(ev.target?.result as string)
        reader.readAsDataURL(file)
    }

    function submitAvatar(e: React.FormEvent) {
        e.preventDefault()
        avatarForm.post('/profile/avatar', { forceFormData: true })
    }

    function removePreview() {
        setAvatarPreview(user.avatar ?? null)
        avatarForm.setData('avatar', null)
        if (fileInputRef.current) fileInputRef.current.value = ''
    }

    const hasPendingUpload = avatarForm.data.avatar !== null

    return (
        <AppLayout title="Profile">
            <div className="w-full px-6 py-8 space-y-8">

                {/* Header */}
                <div className="flex items-center gap-5">
                    <div className="relative group">
                        <Avatar className="size-16">
                            <AvatarImage src={avatarPreview ?? undefined} alt={user.name} />
                            <AvatarFallback className="text-lg font-bold bg-primary/10 text-primary">
                                {getInitials(user.name)}
                            </AvatarFallback>
                        </Avatar>
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="absolute inset-0 flex items-center justify-center rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <CameraIcon className="h-5 w-5 text-white" />
                        </button>
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{user.name}</h1>
                        <p className="text-sm text-muted-foreground capitalize">{user.role} · {user.email}</p>
                    </div>
                </div>

                {/* Avatar upload */}
                <SectionCard
                    title="Profile Photo"
                    description="Upload a photo to personalise your account."
                    icon={CameraIcon}
                >
                    <form onSubmit={submitAvatar} className="space-y-4">
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 rounded-xl border-2 border-dashed border-border flex items-center justify-center bg-muted/30 overflow-hidden shrink-0">
                                {avatarPreview ? (
                                    <img src={avatarPreview} alt="Avatar preview" className="h-full w-full object-cover" />
                                ) : (
                                    <UserIcon className="h-6 w-6 text-muted-foreground/50" />
                                )}
                            </div>
                            <div className="flex-1 space-y-2">
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="gap-1.5"
                                        onClick={() => fileInputRef.current?.click()}
                                    >
                                        <UploadIcon className="h-3.5 w-3.5" />
                                        {avatarPreview ? 'Change photo' : 'Upload photo'}
                                    </Button>
                                    {hasPendingUpload && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="gap-1.5 text-muted-foreground hover:text-destructive"
                                            onClick={removePreview}
                                        >
                                            <XIcon className="h-3.5 w-3.5" />
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                                <p className="text-xs text-muted-foreground">JPG, PNG, GIF or WebP. Max 2 MB.</p>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/jpeg,image/png,image/gif,image/webp"
                                    className="hidden"
                                    onChange={handleAvatarChange}
                                />
                            </div>
                        </div>
                        {avatarForm.errors.avatar && (
                            <p className="text-xs text-destructive">{avatarForm.errors.avatar}</p>
                        )}
                        <div className="flex justify-end">
                            <Button type="submit" disabled={avatarForm.processing || !hasPendingUpload}>
                                {avatarForm.processing ? 'Uploading…' : 'Save photo'}
                            </Button>
                        </div>
                    </form>
                </SectionCard>

                {/* Profile info */}
                <SectionCard
                    title="Profile Information"
                    description="Update your name, email address and personal email signature."
                    icon={UserIcon}
                >
                    <form onSubmit={submitProfile} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="name">Full name</Label>
                            <Input
                                id="name"
                                value={profileForm.data.name}
                                onChange={e => profileForm.setData('name', e.target.value)}
                                required
                                autoComplete="name"
                            />
                            {profileForm.errors.name && (
                                <p className="text-xs text-destructive">{profileForm.errors.name}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                value={profileForm.data.email}
                                onChange={e => profileForm.setData('email', e.target.value)}
                                required
                                autoComplete="email"
                            />
                            {profileForm.errors.email && (
                                <p className="text-xs text-destructive">{profileForm.errors.email}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="signature">Email signature</Label>
                            <textarea
                                id="signature"
                                rows={4}
                                className="flex min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                                value={profileForm.data.signature}
                                onChange={e => profileForm.setData('signature', e.target.value)}
                                placeholder={'Best regards,\nYour Name\nSupport Team'}
                            />
                            <p className="text-xs text-muted-foreground">
                                Appended to your outgoing replies. Overrides the mailbox default signature.
                            </p>
                            {profileForm.errors.signature && (
                                <p className="text-xs text-destructive">{profileForm.errors.signature}</p>
                            )}
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" disabled={profileForm.processing}>
                                {profileForm.processing ? 'Saving…' : 'Save changes'}
                            </Button>
                        </div>
                    </form>
                </SectionCard>

                {/* Change password */}
                <SectionCard
                    title="Change Password"
                    description="Choose a strong password and don't reuse it for other accounts."
                    icon={ShieldIcon}
                >
                    <form onSubmit={submitPassword} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="current_password">Current password</Label>
                            <Input
                                id="current_password"
                                type="password"
                                value={passwordForm.data.current_password}
                                onChange={e => passwordForm.setData('current_password', e.target.value)}
                                required
                                autoComplete="current-password"
                            />
                            {passwordForm.errors.current_password && (
                                <p className="text-xs text-destructive">{passwordForm.errors.current_password}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="password">New password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={passwordForm.data.password}
                                onChange={e => passwordForm.setData('password', e.target.value)}
                                required
                                autoComplete="new-password"
                            />
                            {passwordForm.errors.password && (
                                <p className="text-xs text-destructive">{passwordForm.errors.password}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="password_confirmation">Confirm new password</Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                value={passwordForm.data.password_confirmation}
                                onChange={e => passwordForm.setData('password_confirmation', e.target.value)}
                                required
                                autoComplete="new-password"
                            />
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" disabled={passwordForm.processing}>
                                {passwordForm.processing ? 'Updating…' : 'Update password'}
                            </Button>
                        </div>
                    </form>
                </SectionCard>

            </div>
        </AppLayout>
    )
}
