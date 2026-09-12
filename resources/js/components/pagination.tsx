import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';

export type PaginationLink = {
    url: string | null;
    label: string;
    page: number | null;
    active: boolean;
};

/**
 * De shape waarin Laravel een LengthAwarePaginator naar Inertia serialiseert.
 */
export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    /** De paginator waarvan de links en teller getoond worden. */
    paginator: Paginated<unknown>;
    /** Props die meegaan op elke paginalink, bijv. een partial reload. */
    only?: string[];
};

/**
 * Laravel voegt bij veel pagina's een gat toe als link zonder url en zonder
 * paginanummer ("..."). Die renderen we als tekst in plaats van als knop.
 */
function isEllipsis(link: PaginationLink): boolean {
    return link.url === null && link.page === null;
}

export function Pagination({ paginator, only }: Props) {
    const { t } = useTranslations();

    if (paginator.total === 0) {
        return null;
    }

    /**
     * De eerste en laatste link van Laravel zijn altijd vorige/volgende; hun
     * labels zijn HTML-entities ("&laquo; Previous"), dus we tonen ze als
     * chevrons met een vertaald aria-label.
     */
    const pageLinks = paginator.links.slice(1, -1);
    const previous = paginator.links[0];
    const next = paginator.links[paginator.links.length - 1];

    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p className="text-muted-foreground text-sm">
                {t('Showing :first–:last of :total results', {
                    first: paginator.from ?? 0,
                    last: paginator.to ?? 0,
                    total: paginator.total,
                })}
            </p>

            {paginator.last_page > 1 && (
                <nav aria-label={t('Pagination')}>
                    <ul className="flex flex-wrap items-center gap-1">
                        <li>
                            <Button
                                asChild={previous.url !== null}
                                variant="outline"
                                size="icon"
                                disabled={previous.url === null}
                                aria-label={t('Previous page')}
                            >
                                {previous.url === null ? (
                                    <ChevronLeft />
                                ) : (
                                    <Link
                                        href={previous.url}
                                        only={only}
                                        preserveState
                                        preserveScroll
                                    >
                                        <ChevronLeft />
                                    </Link>
                                )}
                            </Button>
                        </li>

                        {pageLinks.map((link, position) => (
                            <li key={`${link.label}-${position}`}>
                                {isEllipsis(link) ? (
                                    <span className="text-muted-foreground px-2 text-sm">
                                        {link.label}
                                    </span>
                                ) : (
                                    <Button
                                        asChild
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="icon"
                                        aria-current={
                                            link.active ? 'page' : undefined
                                        }
                                        aria-label={t('Page :page', {
                                            page: link.label,
                                        })}
                                    >
                                        <Link
                                            href={link.url ?? '#'}
                                            only={only}
                                            preserveState
                                            preserveScroll
                                        >
                                            {link.label}
                                        </Link>
                                    </Button>
                                )}
                            </li>
                        ))}

                        <li>
                            <Button
                                asChild={next.url !== null}
                                variant="outline"
                                size="icon"
                                disabled={next.url === null}
                                aria-label={t('Next page')}
                            >
                                {next.url === null ? (
                                    <ChevronRight />
                                ) : (
                                    <Link
                                        href={next.url}
                                        only={only}
                                        preserveState
                                        preserveScroll
                                    >
                                        <ChevronRight />
                                    </Link>
                                )}
                            </Button>
                        </li>
                    </ul>
                </nav>
            )}
        </div>
    );
}
