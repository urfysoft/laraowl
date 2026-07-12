import { Link } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';

/**
 * `meta` is the Laravel paginator. Its navigation URLs live on the paginator
 * itself (`first_page_url`, `prev_page_url`, ...); the `links` array only holds
 * the numbered page links. The chevrons used to read `links.first`/`links.prev`,
 * which do not exist on that array, so First/Prev/Next/Last never worked.
 */
export function Pagination({ meta }: { links?: any; meta: any }) {
    if (!meta || meta.last_page <= 1) {
        return null;
    }

    const numberedLinks = (meta.links || []).filter(
        (l: any) => !isNaN(Number(l.label)),
    );

    const arrow = (url: string | null, icon: React.ReactNode, key: string) => {
        const className =
            'rounded p-1.5 transition-colors hover:bg-white/10 flex items-center justify-center';

        if (!url) {
            return (
                <span
                    key={key}
                    className={`${className} cursor-not-allowed opacity-30`}
                    aria-disabled="true"
                >
                    {icon}
                </span>
            );
        }

        return (
            <Link
                key={key}
                href={url}
                preserveScroll
                preserveState
                className={className}
            >
                {icon}
            </Link>
        );
    };

    return (
        <div className="flex items-center justify-between gap-4 border-t border-border bg-white/[0.01] p-4">
            <div className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                Showing {meta.from} to {meta.to} of {meta.total} results
            </div>

            <div className="flex items-center gap-1">
                {arrow(
                    meta.first_page_url,
                    <ChevronsLeft className="h-4 w-4" />,
                    'first',
                )}
                {arrow(
                    meta.prev_page_url,
                    <ChevronLeft className="h-4 w-4" />,
                    'prev',
                )}

                <div className="mx-2 flex items-center gap-1">
                    {numberedLinks.map((link: any) => (
                        <Link
                            key={link.label}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={`flex h-7 min-w-[28px] items-center justify-center rounded text-xs font-bold transition-all ${link.active ? 'border border-border bg-white/10 text-foreground' : 'text-gray-500 hover:bg-muted hover:text-foreground'}`}
                        >
                            {link.label}
                        </Link>
                    ))}
                </div>

                {arrow(
                    meta.next_page_url,
                    <ChevronRight className="h-4 w-4" />,
                    'next',
                )}
                {arrow(
                    meta.last_page_url,
                    <ChevronsRight className="h-4 w-4" />,
                    'last',
                )}
            </div>
        </div>
    );
}
