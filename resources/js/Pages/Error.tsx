import { Head, Link } from '@inertiajs/react';

const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Access Denied',
        description: "You don't have permission to access this page.",
    },
    404: {
        title: 'Page Not Found',
        description: "The page you're looking for doesn't exist or has been moved.",
    },
    500: {
        title: 'Server Error',
        description: 'Something went wrong on our end. Please try again later.',
    },
    503: {
        title: 'Service Unavailable',
        description: "We're down for maintenance. We'll be back shortly.",
    },
};

export default function Error({ status }: { status: number }) {
    const { title, description } = messages[status] ?? {
        title: 'An Error Occurred',
        description: 'Something unexpected happened.',
    };

    return (
        <>
            <Head title={`${status} — ${title}`} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background px-6 text-center">
                <p className="text-sm font-semibold uppercase tracking-widest text-muted-foreground">Error {status}</p>
                <h1 className="mt-4 text-4xl font-bold tracking-tight text-foreground">{title}</h1>
                <p className="mt-4 max-w-md text-base text-muted-foreground">{description}</p>
                <div className="mt-8 flex gap-3">
                    <Link
                        href="/"
                        className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 transition-colors"
                    >
                        Go home
                    </Link>
                    <button
                        onClick={() => window.history.back()}
                        className="inline-flex items-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm hover:bg-accent transition-colors"
                    >
                        Go back
                    </button>
                </div>
            </div>
        </>
    );
}
