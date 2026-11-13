import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';
import MatchDayController from '@/actions/App/Http/Controllers/MatchDayController';
import type { MatchDayProps } from '@/components/competitions/match-day-form';
import MatchDayForm from '@/components/competitions/match-day-form';
import type { MatchDayFieldProps } from '@/components/competitions/match-day-field-manager';
import MatchDayFieldManager from '@/components/competitions/match-day-field-manager';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import { edit as editCompetition, index } from '@/routes/competitions';
import {
    edit as editMatchDay,
    update as updateMatchDay,
} from '@/routes/competitions/match-days';

export type MatchDayMoveImpact = {
    available_count: number;
    scheduled_match_count: number;
    pinned_match_count: number;
    played_match_count: number;
};

type Props = {
    competition: {
        id: number;
        name: string;
        starts_at: string;
        ends_at: string | null;
        availability_open: boolean;
    };
    matchDay: MatchDayProps;
    fields: MatchDayFieldProps[];
    moveImpact: MatchDayMoveImpact;
};

export default function MatchDaysEdit({
    competition,
    matchDay,
    fields,
    moveImpact,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;
    const title = t('Match day :date', {
        date: formatDate(matchDay.date, locale),
    });

    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-1">
                    <Link
                        href={editCompetition(competition.id, {
                            query: { tab: 'match-days' },
                        })}
                        className="text-muted-foreground flex items-center gap-1 text-sm hover:underline"
                    >
                        <ArrowLeft className="size-4" />
                        {t('Back to competition')}
                    </Link>
                    <Heading
                        as="h1"
                        title={title}
                        description={competition.name}
                        className="mb-0"
                    />
                </div>

                <MatchDayForm
                    matchDay={matchDay}
                    action={updateMatchDay([competition.id, matchDay.id]).url}
                    method="put"
                    competitionStartsAt={competition.starts_at}
                    competitionEndsAt={competition.ends_at}
                    submitLabel={t('Save changes')}
                    dateChangeWarning={
                        <MoveWarning
                            impact={moveImpact}
                            oldDate={formatDate(matchDay.date, locale)}
                            availabilityOpen={competition.availability_open}
                        />
                    }
                />

                <MatchDayFieldManager
                    competitionId={competition.id}
                    matchDayId={matchDay.id}
                    fields={fields}
                />

                <div className="max-w-xl border-t pt-6">
                    <ConfirmDialog
                        trigger={
                            <Button variant="destructive">
                                {t('Delete match day')}
                            </Button>
                        }
                        title={t('Delete match day?')}
                        description={t(
                            'This removes the match day and all its fields.',
                        )}
                        action={MatchDayController.destroy.form([
                            competition.id,
                            matchDay.id,
                        ])}
                        confirmLabel={t('Delete match day')}
                    />
                </div>
            </div>
        </>
    );
}

function MoveWarning({
    impact,
    oldDate,
    availabilityOpen,
}: {
    impact: MatchDayMoveImpact;
    oldDate: string;
    availabilityOpen: boolean;
}) {
    const { t } = useTranslations();
    const {
        available_count,
        scheduled_match_count,
        pinned_match_count,
        played_match_count,
    } = impact;

    return (
        <Alert
            role="status"
            className="border-copper/40 bg-copper-soft/40 dark:bg-copper/10 [&>svg]:text-copper-strong dark:[&>svg]:text-copper"
        >
            <TriangleAlert />
            <AlertTitle className="line-clamp-none">
                {t(
                    'Moving this match day clears its availability and schedule',
                )}
            </AlertTitle>
            <AlertDescription className="text-foreground">
                {available_count > 0 && (
                    <p>
                        {available_count === 1
                            ? t(
                                  ':count participant ticked :date. That tick lapses.',
                                  { count: available_count, date: oldDate },
                              )
                            : t(
                                  ':count participants ticked :date. Those ticks lapse.',
                                  { count: available_count, date: oldDate },
                              )}
                    </p>
                )}
                {scheduled_match_count > 0 && (
                    <p>
                        {scheduled_match_count === 1
                            ? t(
                                  ':count scheduled match is taken off the schedule.',
                                  { count: scheduled_match_count },
                              )
                            : t(
                                  ':count scheduled matches are taken off the schedule.',
                                  { count: scheduled_match_count },
                              )}
                        {pinned_match_count > 0 && ' '}
                        {pinned_match_count === 1 &&
                            t(
                                ':count of them is pinned and is taken off the schedule as well.',
                                { count: pinned_match_count },
                            )}
                        {pinned_match_count > 1 &&
                            t(
                                ':count of them are pinned and are taken off the schedule as well.',
                                { count: pinned_match_count },
                            )}
                    </p>
                )}
                {played_match_count > 0 && (
                    <p>
                        {played_match_count === 1
                            ? t(
                                  ':count played match stays on this match day and moves to the new date.',
                                  { count: played_match_count },
                              )
                            : t(
                                  ':count played matches stay on this match day and move to the new date.',
                                  { count: played_match_count },
                              )}
                    </p>
                )}
                <p>
                    {availabilityOpen
                        ? t(
                              'All participants have to submit their availability for the new date again. They see this on their next visit; you can send a reminder on the Availability tab.',
                          )
                        : t(
                              'Participants submit their availability for the new date once the competition is active.',
                          )}
                    {availabilityOpen && scheduled_match_count > 0 && ' '}
                    {availabilityOpen &&
                        scheduled_match_count > 0 &&
                        t(
                            'Schedule these matches again once the availability is in.',
                        )}
                </p>
                <p>
                    {t(
                        'This cannot be undone: changing the date back does not restore the ticks or the schedule.',
                    )}
                </p>
            </AlertDescription>
        </Alert>
    );
}

MatchDaysEdit.layout = ({
    competition,
    matchDay,
    locale,
}: Props & { locale: string }) => ({
    breadcrumbs: [
        { title: 'Competitions', href: index() },
        {
            title: competition.name,
            href: editCompetition(competition.id, {
                query: { tab: 'match-days' },
            }),
            translate: false,
        },
        {
            title: formatDate(matchDay.date, locale),
            href: editMatchDay([competition.id, matchDay.id]),
            translate: false,
        },
    ],
});
