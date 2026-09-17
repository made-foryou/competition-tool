import { Form, usePage } from '@inertiajs/react';
import type { ReactNode, RefObject } from 'react';
import { useId, useState } from 'react';
import MatchScheduleController from '@/actions/App/Http/Controllers/MatchScheduleController';
import type { ScheduleMatchDayProps } from '@/components/competitions/schedule-panel';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';

/**
 * De kleinste vorm die de dialoog van een wedstrijd nodig heeft. Zowel een
 * geplande wedstrijd (`ScheduleMatchProps`) als een ongeplande uit het
 * planningsrapport (`ScheduleUnscheduledProps`) past hierin; die laatste heeft
 * geen tafel en geen begintijd en laat beide velden dus weg.
 */
export type MovableMatch = {
    id: number;
    first_player: string;
    second_player: string;
    field_id?: number | null;
    /** De begintijd in `H:i`. */
    starts_at?: string | null;
};

type Props = {
    competitionId: number;
    match: MovableMatch;
    matchDays: ScheduleMatchDayProps[];
    /** Speeldag waarop de wedstrijd nu staat; `null` voor een ongeplande wedstrijd. */
    currentMatchDayId: number | null;
    /**
     * Het element dat de dialoog opent. Laat dit weg bij een gecontroleerde
     * dialoog (`open` + `onOpenChange`) die vanuit een menu opent: dan bestaat
     * er geen trigger die Radix zelf kan aansturen.
     */
    trigger?: ReactNode;
    /** Alleen bij een gecontroleerde dialoog; anders regelt de dialoog zijn eigen staat. */
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    /**
     * Krijgt de focus terug bij het sluiten. Nodig wanneer de dialoog vanuit
     * een menu opent dat daarbij sluit: het menu-item waar Radix de focus aan
     * terug zou geven bestaat dan niet meer.
     */
    restoreFocusRef?: RefObject<HTMLElement | null>;
};

/**
 * Verplaatst één wedstrijd naar een speeldag, tafel en tijdslot naar keuze.
 *
 * De server zet een verplaatste wedstrijd meteen vast, zodat een volgende
 * planning hem laat staan; dat staat ook in de beschrijving van de dialoog.
 * De harde randvoorwaarden (bestaat het slot, is iedereen vrij) komen terug
 * als veldfout op het veld dat de beheerder kan aanpassen -- de speeldag, de
 * tafel of het tijdslot -- en alle drie de velden tonen die fout.
 */
export default function ScheduleMoveDialog({
    competitionId,
    match,
    matchDays,
    currentMatchDayId,
    trigger,
    open,
    onOpenChange,
    restoreFocusRef,
}: Props) {
    const { t } = useTranslations();
    const [ownOpen, setOwnOpen] = useState(false);

    const isOpen = open ?? ownOpen;
    const setOpen = onOpenChange ?? setOwnOpen;

    return (
        <Dialog open={isOpen} onOpenChange={setOpen}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent
                closeLabel={t('Close')}
                onCloseAutoFocus={(event) => {
                    if (restoreFocusRef === undefined) {
                        return;
                    }

                    event.preventDefault();
                    restoreFocusRef.current?.focus();
                }}
            >
                <DialogHeader>
                    <DialogTitle>{t('Move match')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Pick a match day, a field and a time slot. The match is pinned afterwards, so rescheduling leaves it in place.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                {/* Eigen component, zodat de keuzevelden bij elke keer openen
                op de huidige plek van de wedstrijd starten: Radix hangt de
                inhoud van een gesloten dialoog uit de boom. */}
                <MoveMatchForm
                    competitionId={competitionId}
                    match={match}
                    matchDays={matchDays}
                    currentMatchDayId={currentMatchDayId}
                    onMoved={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

/**
 * De tafel waarop de dialoog opent: de huidige tafel als de wedstrijd al op
 * deze speeldag staat en die tafel er nog is, anders de eerste tafel van de
 * dag. Leeg wanneer de dag geen tafels heeft.
 */
function defaultFieldValue(
    matchDay: ScheduleMatchDayProps | null,
    isCurrentMatchDay: boolean,
    match: MovableMatch,
): string {
    if (matchDay === null) {
        return '';
    }

    const current = isCurrentMatchDay
        ? matchDay.fields.find((field) => field.id === match.field_id)
        : undefined;

    return String(current?.id ?? matchDay.fields[0]?.id ?? '');
}

/**
 * Hetzelfde voor het tijdslot: de huidige begintijd als die op een slotgrens
 * van deze dag valt, anders het eerste slot.
 */
function defaultSlotValue(
    matchDay: ScheduleMatchDayProps | null,
    isCurrentMatchDay: boolean,
    match: MovableMatch,
): string {
    if (matchDay === null) {
        return '';
    }

    const current = isCurrentMatchDay
        ? matchDay.slots.find((slot) => slot.starts_at === match.starts_at)
        : undefined;

    return current?.starts_at ?? matchDay.slots[0]?.starts_at ?? '';
}

type FormProps = {
    competitionId: number;
    match: MovableMatch;
    matchDays: ScheduleMatchDayProps[];
    currentMatchDayId: number | null;
    onMoved: () => void;
};

/**
 * De drie afhankelijke keuzevelden. Ze zijn gecontroleerd, maar hebben wél een
 * `name`: Radix rendert daarvoor een verborgen `<select>` binnen het formulier,
 * dezelfde vorm als `competition-form.tsx` gebruikt. Losse verborgen inputs
 * zijn daardoor niet nodig.
 */
function MoveMatchForm({
    competitionId,
    match,
    matchDays,
    currentMatchDayId,
    onMoved,
}: FormProps) {
    const { t } = useTranslations();
    const { locale } = usePage().props;
    const fieldId = useId();

    const initialMatchDay =
        matchDays.find((matchDay) => matchDay.id === currentMatchDayId) ??
        matchDays[0] ??
        null;
    const initialIsCurrent =
        initialMatchDay !== null && initialMatchDay.id === currentMatchDayId;

    const [matchDayValue, setMatchDayValue] = useState(() =>
        initialMatchDay === null ? '' : String(initialMatchDay.id),
    );
    const [fieldValue, setFieldValue] = useState(() =>
        defaultFieldValue(initialMatchDay, initialIsCurrent, match),
    );
    const [slotValue, setSlotValue] = useState(() =>
        defaultSlotValue(initialMatchDay, initialIsCurrent, match),
    );

    const selectedMatchDay =
        matchDays.find((matchDay) => String(matchDay.id) === matchDayValue) ??
        null;

    /** Een andere speeldag heeft andere tafels en slots, dus beide terug naar de eerste optie. */
    const handleMatchDayChange = (value: string) => {
        const nextMatchDay =
            matchDays.find((matchDay) => String(matchDay.id) === value) ?? null;

        setMatchDayValue(value);
        setFieldValue(defaultFieldValue(nextMatchDay, false, match));
        setSlotValue(defaultSlotValue(nextMatchDay, false, match));
    };

    const hasFields = (selectedMatchDay?.fields.length ?? 0) > 0;
    const hasSlots = (selectedMatchDay?.slots.length ?? 0) > 0;

    /**
     * Waarom deze speeldag niets te kiezen heeft, en onder welk veld die
     * reden hoort: het veld dat daardoor leeg blijft. Zonder dat verband
     * leest een losse regel onder alle velden als algemene uitleg in plaats
     * van als de reden dat het formulier niet verstuurd kan worden.
     */
    const unavailable = (() => {
        if (selectedMatchDay === null) {
            return null;
        }

        if (!hasFields) {
            return {
                field: 'field' as const,
                text: t('This match day has no fields yet.'),
            };
        }

        if (!hasSlots) {
            return {
                field: 'slot' as const,
                text: `${t('No slot fits in the opening hours of this match day.')} ${t('Shorten the match duration or extend the opening hours in the planning settings.')}`,
            };
        }

        return null;
    })();

    const unavailableId = `${fieldId}-unavailable`;

    /** Zonder tafel of zonder tijdslot valt er niets te versturen. */
    const canSubmit = hasFields && hasSlots;

    return (
        <Form
            {...MatchScheduleController.update.form([competitionId, match.id])}
            options={{ preserveScroll: true }}
            onSuccess={onMoved}
            className="flex flex-col gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <p className="text-sm">
                        {match.first_player}
                        <span className="text-muted-foreground"> – </span>
                        {match.second_player}
                    </p>

                    <div className="grid gap-2">
                        <Label htmlFor={`${fieldId}-match-day`}>
                            {t('Match day')}
                        </Label>
                        <Select
                            name="match_day_id"
                            value={matchDayValue}
                            onValueChange={handleMatchDayChange}
                        >
                            <SelectTrigger
                                id={`${fieldId}-match-day`}
                                className="w-full"
                                aria-invalid={!!errors.match_day_id}
                                aria-describedby={
                                    errors.match_day_id
                                        ? `${fieldId}-match-day-error`
                                        : undefined
                                }
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {matchDays.map((matchDay) => (
                                    <SelectItem
                                        key={matchDay.id}
                                        value={String(matchDay.id)}
                                    >
                                        {formatDate(matchDay.date, locale)}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {matchDay.starts_at} –{' '}
                                            {matchDay.ends_at}
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            id={`${fieldId}-match-day-error`}
                            message={errors.match_day_id}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${fieldId}-field`}>{t('Field')}</Label>
                        {/* De `key` op de speeldag is nodig omdat de tafels
                        per speeldag andere ids hebben: bij het wisselen van
                        dag unmounten alle `SelectItem`s van de vorige dag en
                        raakt Radix daarmee de gecontroleerde waarde kwijt --
                        het verborgen `<select>` verstuurde dan een lege
                        `match_day_field_id`. Met de key monteert de Select
                        opnieuw, meteen met de nieuwe beginwaarde. De slots
                        houden hun waarde wél vast, omdat een tijd als "19:00"
                        op elke dag dezelfde `value` heeft. */}
                        <Select
                            key={matchDayValue}
                            name="match_day_field_id"
                            value={fieldValue}
                            onValueChange={setFieldValue}
                            disabled={!hasFields}
                        >
                            <SelectTrigger
                                id={`${fieldId}-field`}
                                className="w-full"
                                aria-invalid={!!errors.match_day_field_id}
                                aria-describedby={
                                    [
                                        errors.match_day_field_id
                                            ? `${fieldId}-field-error`
                                            : null,
                                        unavailable?.field === 'field'
                                            ? unavailableId
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' ') || undefined
                                }
                            >
                                <SelectValue
                                    placeholder={t('Choose a field')}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {selectedMatchDay?.fields.map((field) => (
                                    <SelectItem
                                        key={field.id}
                                        value={String(field.id)}
                                    >
                                        {field.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            id={`${fieldId}-field-error`}
                            message={errors.match_day_field_id}
                        />
                        {unavailable?.field === 'field' && (
                            <p
                                id={unavailableId}
                                className="text-destructive text-sm"
                            >
                                {unavailable.text}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${fieldId}-slot`}>
                            {t('Time slot')}
                        </Label>
                        <Select
                            name="starts_at"
                            value={slotValue}
                            onValueChange={setSlotValue}
                            disabled={!hasSlots}
                        >
                            <SelectTrigger
                                id={`${fieldId}-slot`}
                                className="w-full"
                                aria-invalid={!!errors.starts_at}
                                aria-describedby={
                                    [
                                        errors.starts_at
                                            ? `${fieldId}-slot-error`
                                            : null,
                                        unavailable?.field === 'slot'
                                            ? unavailableId
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' ') || undefined
                                }
                            >
                                <SelectValue
                                    placeholder={t('Choose a time slot')}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {selectedMatchDay?.slots.map((slot) => (
                                    <SelectItem
                                        key={slot.index}
                                        value={slot.starts_at}
                                    >
                                        {slot.starts_at} – {slot.ends_at}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            id={`${fieldId}-slot-error`}
                            message={errors.starts_at}
                        />
                        {unavailable?.field === 'slot' && (
                            <p
                                id={unavailableId}
                                className="text-destructive text-sm"
                            >
                                {unavailable.text}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('Cancel')}
                            </Button>
                        </DialogClose>
                        {/* `aria-disabled` in plaats van `disabled` zodra de
                        speeldag onbruikbaar is: een echt uitgeschakelde knop
                        krijgt geen focus en dan leest niemand de reden die er
                        via `aria-describedby` aan hangt. Zelfde patroon als de
                        inplanknop in `schedule-panel.tsx`. */}
                        <Button
                            type="submit"
                            disabled={processing}
                            aria-disabled={!canSubmit}
                            aria-describedby={
                                unavailable === null ? undefined : unavailableId
                            }
                            className="aria-disabled:pointer-events-auto aria-disabled:opacity-50"
                            onClick={(event) => {
                                if (!canSubmit) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            {processing && <Spinner />}
                            {t('Move match')}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}
