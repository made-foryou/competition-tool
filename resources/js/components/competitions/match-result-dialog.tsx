import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useId, useState } from 'react';
import MatchResultController from '@/actions/App/Http/Controllers/MatchResultController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';

/**
 * De kleinste vorm die de uitslagdialoog van een wedstrijd nodig heeft. De
 * scores zijn de gewonnen games per speler en allebei `null` zolang er geen
 * uitslag is.
 */
export type ResultMatch = {
    id: number;
    first_player: string;
    second_player: string;
    first_player_score: number | null;
    second_player_score: number | null;
};

type Props = {
    competitionId: number;
    match: ResultMatch;
    /** Het aantal games dat de winnaar nodig heeft; ook de hoogste geldige score. */
    gamesToWin: number;
    bestOf: number;
    /**
     * Het element dat de dialoog opent. Laat dit weg bij een gecontroleerde
     * dialoog (`open` + `onOpenChange`).
     */
    trigger?: ReactNode;
    /** Alleen bij een gecontroleerde dialoog; anders regelt de dialoog zijn eigen staat. */
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

/**
 * Legt de uitslag van één wedstrijd vast, of corrigeert hem: met een
 * bestaande uitslag opent de dialoog voorgevuld en overschrijft opslaan die.
 *
 * Elke score is het aantal gewonnen games. De server controleert het format:
 * een fout op één score staat bij dat veld, een fout over het paar (geen of
 * twee winnaars) komt terug als `score` en staat onder beide velden.
 */
export default function MatchResultDialog({
    competitionId,
    match,
    gamesToWin,
    bestOf,
    trigger,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslations();
    const [ownOpen, setOwnOpen] = useState(false);

    const isOpen = open ?? ownOpen;
    const setOpen = onOpenChange ?? setOwnOpen;

    const hasResult = match.first_player_score !== null;

    return (
        <Dialog open={isOpen} onOpenChange={setOpen}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent closeLabel={t('Close')}>
                <DialogHeader>
                    <DialogTitle>
                        {hasResult ? t('Edit result') : t('Enter result')}
                    </DialogTitle>
                    <DialogDescription>
                        {gamesToWin === 1
                            ? t(
                                  'Best of :best_of: the winner has :games game.',
                                  { best_of: bestOf, games: gamesToWin },
                              )
                            : t(
                                  'Best of :best_of: the winner has :games games.',
                                  { best_of: bestOf, games: gamesToWin },
                              )}
                        {hasResult && (
                            <> {t('Saving overwrites the current result.')}</>
                        )}
                    </DialogDescription>
                </DialogHeader>
                {/* Eigen component, zodat de velden bij elke keer openen op
                de opgeslagen uitslag starten in plaats van op wat er de vorige
                keer half ingevuld bleef: Radix hangt de inhoud van een
                gesloten dialoog uit de boom. */}
                <MatchResultForm
                    competitionId={competitionId}
                    match={match}
                    gamesToWin={gamesToWin}
                    onSaved={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

type FormProps = {
    competitionId: number;
    match: ResultMatch;
    gamesToWin: number;
    onSaved: () => void;
};

/**
 * De twee scorevelden, ongecontroleerd met de bestaande uitslag als
 * beginwaarde. Na een validatiefout blijft staan wat de beheerder invulde.
 *
 * De paarfout (`score`) hoort bij beide velden: geen van beide scores is op
 * zichzelf fout, maar samen kloppen ze niet. Beide velden zijn dan ongeldig
 * en verwijzen naar die ene melding, zodat een schermlezer hem bij elk veld
 * voorleest.
 */
function MatchResultForm({
    competitionId,
    match,
    gamesToWin,
    onSaved,
}: FormProps) {
    const { t } = useTranslations();
    const fieldId = useId();

    const firstId = `${fieldId}-first`;
    const secondId = `${fieldId}-second`;
    const scoreErrorId = `${fieldId}-score-error`;

    return (
        <Form
            {...MatchResultController.update.form([competitionId, match.id])}
            options={{ preserveScroll: true }}
            onSuccess={onSaved}
            className="flex flex-col gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid content-start gap-2">
                            <Label htmlFor={firstId}>
                                {match.first_player}
                            </Label>
                            <Input
                                id={firstId}
                                name="first_player_score"
                                type="number"
                                inputMode="numeric"
                                min={0}
                                max={gamesToWin}
                                step={1}
                                required
                                autoFocus
                                defaultValue={match.first_player_score ?? ''}
                                className="tabular-nums"
                                aria-invalid={
                                    !!errors.first_player_score ||
                                    !!errors.score
                                }
                                aria-describedby={
                                    [
                                        errors.first_player_score
                                            ? `${firstId}-error`
                                            : null,
                                        errors.score ? scoreErrorId : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' ') || undefined
                                }
                            />
                            <InputError
                                id={`${firstId}-error`}
                                message={errors.first_player_score}
                            />
                        </div>

                        <div className="grid content-start gap-2">
                            <Label htmlFor={secondId}>
                                {match.second_player}
                            </Label>
                            <Input
                                id={secondId}
                                name="second_player_score"
                                type="number"
                                inputMode="numeric"
                                min={0}
                                max={gamesToWin}
                                step={1}
                                required
                                defaultValue={match.second_player_score ?? ''}
                                className="tabular-nums"
                                aria-invalid={
                                    !!errors.second_player_score ||
                                    !!errors.score
                                }
                                aria-describedby={
                                    [
                                        errors.second_player_score
                                            ? `${secondId}-error`
                                            : null,
                                        errors.score ? scoreErrorId : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' ') || undefined
                                }
                            />
                            <InputError
                                id={`${secondId}-error`}
                                message={errors.second_player_score}
                            />
                        </div>
                    </div>

                    <InputError id={scoreErrorId} message={errors.score} />

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('Cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t('Save result')}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}
