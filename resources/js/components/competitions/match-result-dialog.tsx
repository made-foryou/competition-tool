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
    /** `played` zodra er een uitslag is; bepaalt of dit invoeren of corrigeren is. */
    status: string;
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
     * Waar zodra de competitie minstens één uitslag heeft. Zolang dat niet zo
     * is, vergrendelt deze uitslag het format, en dat zegt de dialoog erbij.
     */
    isFormatLocked: boolean;
    /** Het element dat de dialoog opent. */
    trigger: ReactNode;
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
    isFormatLocked,
    trigger,
}: Props) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(false);

    // De status, niet de score: dezelfde bron als `isPlayed()` op de server.
    const hasResult = match.status === 'played';

    const formatReplacements = {
        first: match.first_player,
        second: match.second_player,
        best_of: bestOf,
        games: gamesToWin,
        max_loser: gamesToWin - 1,
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent closeLabel={t('Close')}>
                <DialogHeader>
                    <DialogTitle>
                        {hasResult ? t('Edit result') : t('Enter result')}
                    </DialogTitle>
                    <DialogDescription>
                        {gamesToWin === 1
                            ? t(
                                  ':first – :second · Best of :best_of: the winner has :games game, the loser 0.',
                                  formatReplacements,
                              )
                            : t(
                                  ':first – :second · Best of :best_of: the winner has :games games, the loser 0 to :max_loser.',
                                  formatReplacements,
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
                    formatLockNotice={
                        isFormatLocked
                            ? null
                            : t(
                                  'After this first result the match format (best of :best_of) and the points system are locked.',
                                  { best_of: bestOf },
                              )
                    }
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
    /** De waarschuwing dat deze eerste uitslag het format vastlegt, of `null`. */
    formatLockNotice: string | null;
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
 *
 * Bij focus selecteert een veld zijn huidige waarde: bij corrigeren vervangt
 * een getypt cijfer dan de oude score, in plaats van er "31" van te maken.
 */
function MatchResultForm({
    competitionId,
    match,
    gamesToWin,
    formatLockNotice,
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
                                onFocus={(event) =>
                                    event.currentTarget.select()
                                }
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
                                onFocus={(event) =>
                                    event.currentTarget.select()
                                }
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

                    {formatLockNotice && (
                        <p className="text-muted-foreground text-sm">
                            {formatLockNotice}
                        </p>
                    )}

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
