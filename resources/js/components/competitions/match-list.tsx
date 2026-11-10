import { usePage } from '@inertiajs/react';
import { Pin, Swords } from 'lucide-react';
import MatchResultController from '@/actions/App/Http/Controllers/MatchResultController';
import MatchResultDialog from '@/components/competitions/match-result-dialog';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import { matchStatusBadgeVariant, matchStatusLabel } from '@/lib/match-status';
import { pluralize } from '@/lib/plural';
import { schedulingFailureLabel } from '@/lib/scheduling-failure';
import { cn } from '@/lib/utils';

/**
 * Achtergrond op de sticky tabelkop, zodat wedstrijdrijen er bij verticaal
 * scrollen niet doorheen schemeren (ook niet in dark mode). Effen
 * `bg-background` in plaats van een halftransparante kleur, om dezelfde
 * reden als de sticky kolom in `availability-matrix.tsx`.
 */
const STICKY_HEADER_CLASSES = 'sticky top-0 z-10 bg-background';

/**
 * Vaste breedte voor de actiekolom, op kop én cellen. Een gespeelde
 * wedstrijd krijgt naast "Uitslag wijzigen" ook "Wissen"; zonder vaste
 * breedte groeit de kolom zodra de eerste uitslag binnenkomt en verspringen
 * alle andere kolommen. De breedte past beide knoppen naast elkaar.
 */
const ACTIONS_COLUMN_CLASSES = 'w-60 whitespace-nowrap';

export type MatchProps = {
    id: number;
    first_player: string;
    first_player_is_participant: boolean;
    second_player: string;
    second_player_is_participant: boolean;
    status: string;
    /** Gewonnen games van de eerste speler; `null` zolang er geen uitslag is. */
    first_player_score: number | null;
    /** Gewonnen games van de tweede speler; `null` zolang er geen uitslag is. */
    second_player_score: number | null;
    match_day_date: string | null;
    field_name: string | null;
    starts_at: string | null;
    ends_at: string | null;
    is_pinned: boolean;
    /** De reden waarom de planner deze wedstrijd niet kon plaatsen, `null` als hij nooit geprobeerd is. */
    scheduling_failure: string | null;
};

/**
 * Het wedstrijdformat van de competitie, zoals de editpagina het meekrijgt.
 * Niet uitgesteld, want ook het instellingenformulier heeft `is_locked` nodig.
 */
export type MatchFormatProps = {
    /** Het maximale aantal games per wedstrijd: best of 1, 3, 5 of 7. */
    best_of: number;
    /** Het aantal games dat de winnaar nodig heeft; ook de hoogste geldige score. */
    games_to_win: number;
    /** Waar zodra er minstens één uitslag is: het format ligt dan vast. */
    is_locked: boolean;
};

type Props = {
    competitionId: number;
    matches: MatchProps[];
    /**
     * Of uitslagen ingevoerd, gecorrigeerd en gewist mogen worden. Staat dit
     * uit, dan blijft de lijst gewoon leesbaar maar verdwijnt de actiekolom.
     */
    allowsResultChanges: boolean;
    /** Alleen voor de uitleg waarom uitslagen (nog) niet kunnen. */
    competitionStatus: string;
    matchFormat: MatchFormatProps;
    /** Springt naar de tab Deelnemers. Toont een CTA in de lege staat wanneer meegegeven. */
    onNavigateToParticipants?: () => void;
};

export default function MatchList({
    competitionId,
    matches,
    allowsResultChanges,
    competitionStatus,
    matchFormat,
    onNavigateToParticipants,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    if (matches.length === 0) {
        return (
            <EmptyState
                icon={Swords}
                title={t('No matches yet.')}
                description={t(
                    'Matches appear automatically once the competition has at least two participants.',
                )}
                action={
                    onNavigateToParticipants && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={onNavigateToParticipants}
                        >
                            {t('Go to participants')}
                        </Button>
                    )
                }
            />
        );
    }

    const playedCount = matches.filter(
        (match) => match.status === 'played',
    ).length;

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-col gap-1">
                <h3 className="text-sm font-medium">{t('Matches')}</h3>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'The match list updates automatically whenever participants are added or removed.',
                        )}
                    </p>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline">
                            {t(':played of :total played', {
                                played: playedCount,
                                total: matches.length,
                            })}
                        </Badge>
                        <Badge variant="secondary">
                            {pluralize(
                                t,
                                matches.length,
                                ':count match',
                                ':count matches',
                            )}
                        </Badge>
                    </div>
                </div>
                {!allowsResultChanges && (
                    <p className="text-muted-foreground text-sm">
                        {competitionStatus === 'finished'
                            ? t(
                                  'This competition is finished. To enter or change results, temporarily set its status back to Active under General.',
                              )
                            : t(
                                  'Results can only be entered for an active competition. Change the status under General.',
                              )}
                    </p>
                )}
            </div>

            <div className="rounded-xl border">
                <Table aria-label={t('Match list')}>
                    <TableHeader>
                        <TableRow>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_HEADER_CLASSES)}
                            >
                                {t('Player 1')}
                            </TableHead>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_HEADER_CLASSES)}
                            >
                                {t('Player 2')}
                            </TableHead>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_HEADER_CLASSES)}
                            >
                                {t('Result')}
                            </TableHead>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_HEADER_CLASSES)}
                            >
                                {t('Scheduled')}
                            </TableHead>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_HEADER_CLASSES)}
                            >
                                {t('Status')}
                            </TableHead>
                            {allowsResultChanges && (
                                <TableHead
                                    scope="col"
                                    className={cn(
                                        'p-3',
                                        STICKY_HEADER_CLASSES,
                                        ACTIONS_COLUMN_CLASSES,
                                    )}
                                >
                                    <span className="sr-only">
                                        {t('Actions')}
                                    </span>
                                </TableHead>
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {matches.map((match) => (
                            <TableRow key={match.id}>
                                <TableCell className="p-3 whitespace-normal">
                                    {match.first_player}
                                    {!match.first_player_is_participant && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2"
                                        >
                                            {t('No longer participating')}
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell className="p-3 whitespace-normal">
                                    {match.second_player}
                                    {!match.second_player_is_participant && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2"
                                        >
                                            {t('No longer participating')}
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell className="p-3 align-top">
                                    <MatchScore match={match} />
                                </TableCell>
                                <TableCell className="p-3 align-top">
                                    {match.match_day_date ? (
                                        <div className="flex flex-col gap-0.5">
                                            <span className="flex items-center gap-1.5">
                                                {formatDate(
                                                    match.match_day_date,
                                                    locale,
                                                )}
                                                {match.is_pinned && (
                                                    <>
                                                        <Pin className="text-muted-foreground size-3.5" />
                                                        <span className="sr-only">
                                                            {t('Pinned')}
                                                        </span>
                                                    </>
                                                )}
                                            </span>
                                            <span className="text-muted-foreground text-xs">
                                                {match.starts_at} –{' '}
                                                {match.ends_at} ·{' '}
                                                {match.field_name ??
                                                    t('No field')}
                                            </span>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col gap-0.5">
                                            <span className="text-muted-foreground">
                                                {t('Not scheduled')}
                                            </span>
                                            {match.scheduling_failure !==
                                                null && (
                                                <span className="text-muted-foreground text-xs">
                                                    {schedulingFailureLabel(
                                                        match.scheduling_failure,
                                                        t,
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </TableCell>
                                <TableCell className="p-3 align-top">
                                    <Badge
                                        variant={matchStatusBadgeVariant(
                                            match.status,
                                        )}
                                    >
                                        {matchStatusLabel(match.status, t)}
                                    </Badge>
                                </TableCell>
                                {allowsResultChanges && (
                                    <TableCell
                                        className={cn(
                                            'px-3 py-2 align-top',
                                            ACTIONS_COLUMN_CLASSES,
                                        )}
                                    >
                                        <MatchResultActions
                                            competitionId={competitionId}
                                            match={match}
                                            matchFormat={matchFormat}
                                        />
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </section>
    );
}

/**
 * Hoe de score van één kant oogt: de winnaar iets nadrukkelijker, de
 * verliezer gedempt. Bij een (ongeldig) gelijk paar geen van beide.
 */
function scoreClassName(own: number, other: number): string | undefined {
    if (own > other) {
        return 'font-semibold';
    }

    if (own < other) {
        return 'text-muted-foreground';
    }

    return undefined;
}

/**
 * De uitslag in de lijst, in dezelfde volgorde als de spelerskolommen. Zonder
 * uitslag een gedempt streepje: dat is visueel rustiger dan een tekst in elke
 * openstaande rij, en de verborgen tekst vertelt een schermlezer wat het
 * streepje betekent.
 */
function MatchScore({ match }: { match: MatchProps }) {
    const { t } = useTranslations();
    const first = match.first_player_score;
    const second = match.second_player_score;

    if (first === null || second === null) {
        return (
            <>
                <span aria-hidden="true" className="text-muted-foreground">
                    –
                </span>
                <span className="sr-only">{t('No result yet')}</span>
            </>
        );
    }

    return (
        <span className="font-medium tabular-nums">
            <span className={scoreClassName(first, second)}>{first}</span>
            <span className="text-muted-foreground"> – </span>
            <span className={scoreClassName(second, first)}>{second}</span>
        </span>
    );
}

type MatchResultActionsProps = {
    competitionId: number;
    match: MatchProps;
    matchFormat: MatchFormatProps;
};

/**
 * Invoeren of corrigeren, en bij een gespeelde wedstrijd ook wissen.
 *
 * Elke rij heeft dezelfde knopteksten, dus de spelers staan er verborgen
 * achter: zo houdt een schermlezer de knoppen uit elkaar, terwijl de
 * zichtbare tekst het begin van de toegankelijke naam blijft (WCAG 2.5.3,
 * spraakbediening).
 *
 * "Heeft een uitslag" volgt de status, net als `isPlayed()` op de server: één
 * bron voor wat de lijst toont en wat de server toestaat.
 */
function MatchResultActions({
    competitionId,
    match,
    matchFormat,
}: MatchResultActionsProps) {
    const { t } = useTranslations();
    const hasResult = match.status === 'played';
    const removesMatch =
        !match.first_player_is_participant ||
        !match.second_player_is_participant;

    return (
        <div className="flex items-center gap-1">
            <MatchResultDialog
                competitionId={competitionId}
                match={match}
                gamesToWin={matchFormat.games_to_win}
                bestOf={matchFormat.best_of}
                isFormatLocked={matchFormat.is_locked}
                trigger={
                    <Button variant="outline" size="sm">
                        {hasResult ? t('Edit result') : t('Enter result')}
                        <span className="sr-only">
                            : {match.first_player} – {match.second_player}
                        </span>
                    </Button>
                }
            />
            {hasResult && (
                <ConfirmDialog
                    trigger={
                        <Button variant="ghostDestructive" size="sm">
                            {t('Clear')}
                            <span className="sr-only">
                                :{' '}
                                {t('result :first – :second', {
                                    first: match.first_player,
                                    second: match.second_player,
                                })}
                            </span>
                        </Button>
                    }
                    title={t('Clear result?')}
                    description={clearResultDescription(match, t)}
                    action={MatchResultController.destroy.form([
                        competitionId,
                        match.id,
                    ])}
                    confirmLabel={
                        removesMatch
                            ? t('Clear result and remove match')
                            : t('Clear result')
                    }
                />
            )}
        </div>
    );
}

/**
 * Wat wissen precies doet, per situatie. Neemt een van de spelers niet meer
 * deel, dan houdt na het wissen niets de wedstrijd nog in de lijst: de sync
 * verwijdert hem, en dat is niet terug te draaien. Dat moet de beheerder
 * vóór het bevestigen weten.
 */
function clearResultDescription(
    match: MatchProps,
    t: ReturnType<typeof useTranslations>['t'],
): string {
    const replacements = {
        score: `${match.first_player_score ?? '–'}–${match.second_player_score ?? '–'}`,
        first: match.first_player,
        second: match.second_player,
    };

    if (
        !match.first_player_is_participant &&
        !match.second_player_is_participant
    ) {
        return t(
            'This clears the result :score of :first – :second. Neither player participates any longer, so the match is then removed from the list. This cannot be undone.',
            replacements,
        );
    }

    if (
        !match.first_player_is_participant ||
        !match.second_player_is_participant
    ) {
        return t(
            'This clears the result :score of :first – :second. :player no longer participates, so the match is then removed from the list. This cannot be undone.',
            {
                ...replacements,
                player: match.first_player_is_participant
                    ? match.second_player
                    : match.first_player,
            },
        );
    }

    return t(
        'This clears the result :score of :first – :second. The match goes back to "To play".',
        replacements,
    );
}
