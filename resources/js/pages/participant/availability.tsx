import { Form, Head, Link } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import type { MatchDayOption } from '@/components/match-day-checklist';
import MatchDayChecklist from '@/components/match-day-checklist';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { dashboard } from '@/routes/competition';
import { update } from '@/routes/competition/availability';

type AvailabilityState = 'open' | 'upcoming' | 'closed' | 'not-participating';

type Props = {
    competition: { name: string; slug: string };
    matchDays: MatchDayOption[];
    hasSubmitted: boolean;
    availabilityState: AvailabilityState;
};

export default function ParticipantAvailability({
    competition,
    matchDays,
    hasSubmitted,
    availabilityState,
}: Props) {
    const { t } = useTranslations();

    const isOpen = availabilityState === 'open';

    const explanationByState: Record<AvailabilityState, string> = {
        open: '',
        closed: t(
            'This competition has finished. Below you can see the match days you were available on. Contact the organizer if you have any questions.',
        ),
        upcoming: t(
            'This competition has not opened yet. You can fill in your availability as soon as it starts.',
        ),
        'not-participating': t(
            'You do not take part in this competition, so there is no availability to show.',
        ),
    };

    // Een vinkje is een antwoord, ook zonder inzending: dat komt voor als er na
    // de laatste inzending een speeldag bij kwam. Dit geldt voor de open staat
    // (uitleg over opnieuw opslaan) en voor de alleen-lezenstaat (de lijst
    // tonen in plaats van "je hebt niets ingevuld").
    const hasEarlierAnswers = matchDays.some(
        (matchDay) => matchDay.is_available,
    );

    // De uitleg over de competitiestatus klopt alleen als er ook echt een
    // ingevulde lijst onder staat. Zonder enig antwoord tonen we die regel dus
    // niet, maar de melding dat er niets is ingevuld.
    const showMatchDays =
        !isOpen &&
        (hasSubmitted || hasEarlierAnswers) &&
        (availabilityState === 'closed' || availabilityState === 'upcoming');

    const newMatchDayCount = matchDays.filter(
        (matchDay) => matchDay.is_new,
    ).length;

    const readOnlyExplanation =
        availabilityState === 'not-participating' ||
        hasSubmitted ||
        hasEarlierAnswers
            ? explanationByState[availabilityState]
            : t('You did not fill in your availability for this competition.');

    return (
        <>
            <Head title={t('My availability')} />

            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-1">
                    <h1 className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                        <CalendarCheck className="size-5 shrink-0" />
                        {t('My availability')}
                    </h1>
                    {isOpen ? (
                        <p className="text-muted-foreground text-sm">
                            {hasSubmitted
                                ? t('You can change this at any time.')
                                : newMatchDayCount === 1
                                  ? t(
                                        'A match day was added to :competition. Check whether you can attend the new match day and save your availability again to continue.',
                                        { competition: competition.name },
                                    )
                                  : newMatchDayCount > 1
                                    ? t(
                                          'Match days were added to :competition. Check whether you can attend the new match days and save your availability again to continue.',
                                          { competition: competition.name },
                                      )
                                    : t(
                                          'You take part in :competition. Fill in your availability to continue.',
                                          { competition: competition.name },
                                      )}
                        </p>
                    ) : (
                        <p
                            role="status"
                            className="text-muted-foreground text-sm"
                        >
                            {readOnlyExplanation}
                        </p>
                    )}
                </div>

                {isOpen && (
                    <Form
                        {...update.form({ competition: competition.slug })}
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <MatchDayChecklist matchDays={matchDays} />
                                <InputError message={errors.match_days} />

                                {matchDays.length > 0 && (
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            'Leave everything unchecked if you cannot attend any match day.',
                                        )}
                                    </p>
                                )}

                                <div>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        {t('Save availability')}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                {showMatchDays && (
                    <MatchDayChecklist matchDays={matchDays} readOnly />
                )}

                {/*
                 * Zonder opslaanknop is deze pagina doodlopend, dus hoort er
                 * een weg terug te zijn. In de open staat juist niet: wie zijn
                 * beschikbaarheid nog moet indienen wordt door
                 * EnsureAvailabilityIsSubmitted meteen teruggestuurd naar dit
                 * formulier, dus die link zou een lus zijn.
                 */}
                {!isOpen && (
                    <div>
                        <Button asChild variant="secondary" size="sm">
                            <Link href={dashboard(competition.slug)}>
                                {t('Back to your dashboard')}
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
