import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
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
import {
    pointsSystemDescription,
    pointsSystemLabel,
} from '@/lib/points-system';
import type { PointsSystemValue } from '@/lib/points-system';

export type CompetitionSettingsProps = {
    match_duration_minutes: number;
    buffer_minutes: number;
    min_rest_minutes: number;
    break_duration_minutes: number;
    use_pools: boolean;
    pool_size: number | null;
    max_matches_per_player_per_day: number;
    /** Het maximale aantal games per wedstrijd. */
    best_of: number;
    /** De puntentelling voor het klassement. */
    points_system: PointsSystemValue;
};

export type CompetitionSettingsLimits = {
    match_duration_minutes: { min: number; max: number };
    buffer_minutes: { min: number; max: number };
    min_rest_minutes: { min: number; max: number };
    break_duration_minutes: { min: number; max: number };
    pool_size: { min: number; max: number; default: number };
    max_matches_per_player_per_day: { min: number; max: number };
    best_of: { options: number[]; default: number };
    points_system: {
        options: PointsSystemValue[];
        default: PointsSystemValue;
    };
};

type Props = {
    settings: CompetitionSettingsProps;
    limits: CompetitionSettingsLimits;
    /**
     * Waar zodra er een uitslag is: dan liggen het wedstrijdformat en de
     * puntentelling vast, omdat de bestaande uitslagen anders niet meer bij
     * het format passen en de stand zou verschuiven.
     */
    matchFormatLocked: boolean;
    action: string;
};

/** Het aantal games dat de winnaar nodig heeft: de helft plus één. */
function gamesToWin(bestOf: number): number {
    return Math.floor(bestOf / 2) + 1;
}

export default function CompetitionSettingsForm({
    settings,
    limits,
    matchFormatLocked,
    action,
}: Props) {
    const { t } = useTranslations();
    const [usePools, setUsePools] = useState(settings.use_pools);
    const [poolSize, setPoolSize] = useState(
        String(settings.pool_size ?? limits.pool_size.default),
    );
    const [bestOf, setBestOf] = useState(String(settings.best_of));
    const [pointsSystem, setPointsSystem] = useState<string>(
        settings.points_system,
    );

    const games = gamesToWin(Number(bestOf));
    const winnerHint =
        games === 1
            ? t('The winner is the first player to win :games game.', {
                  games,
              })
            : t('The winner is the first player to win :games games.', {
                  games,
              });

    return (
        <Form
            action={action}
            method="put"
            className="flex max-w-xl flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    {/* Het format staat vóór Timing: het is de meest
                    fundamentele eigenschap van een wedstrijd, en de
                    wedstrijdduur hieronder hangt ervan af. */}
                    <div className="flex flex-col gap-3">
                        <h3 className="text-sm font-medium">
                            {t('Match format')}
                        </h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {matchFormatLocked ? (
                                <div className="grid gap-2">
                                    {/* Vergrendeld is er niets te kiezen,
                                    dus geen uitgeschakelde Select maar de
                                    waarde als tekst. Die komt uit de prop:
                                    dat is wat de server heeft, en precies
                                    wat de verborgen input meestuurt (de
                                    server vereist `best_of` altijd). */}
                                    <input
                                        type="hidden"
                                        name="best_of"
                                        value={settings.best_of}
                                    />
                                    <dl className="grid gap-2">
                                        <Label asChild>
                                            <dt>{t('Format')}</dt>
                                        </Label>
                                        <dd className="text-sm">
                                            {t('Best of :count', {
                                                count: settings.best_of,
                                            })}
                                        </dd>
                                    </dl>
                                    <InputError
                                        id="best_of-error"
                                        message={errors.best_of}
                                    />
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="best_of">
                                        {t('Format')}
                                    </Label>
                                    <Select
                                        name="best_of"
                                        value={bestOf}
                                        onValueChange={setBestOf}
                                    >
                                        <SelectTrigger
                                            id="best_of"
                                            className="w-full"
                                            aria-invalid={!!errors.best_of}
                                            aria-describedby={[
                                                errors.best_of
                                                    ? 'best_of-error'
                                                    : null,
                                                'best_of-hint',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {limits.best_of.options.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option}
                                                        value={String(option)}
                                                    >
                                                        {t('Best of :count', {
                                                            count: option,
                                                        })}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        id="best_of-error"
                                        message={errors.best_of}
                                    />
                                    <p
                                        id="best_of-hint"
                                        className="text-muted-foreground text-sm"
                                    >
                                        {winnerHint}
                                    </p>
                                </div>
                            )}
                            {matchFormatLocked ? (
                                <div className="grid gap-2">
                                    <input
                                        type="hidden"
                                        name="points_system"
                                        value={settings.points_system}
                                    />
                                    <dl className="grid gap-2">
                                        <Label asChild>
                                            <dt>{t('Points system')}</dt>
                                        </Label>
                                        <dd className="text-sm">
                                            {pointsSystemLabel(
                                                settings.points_system,
                                                t,
                                            )}
                                        </dd>
                                    </dl>
                                    <InputError
                                        id="points_system-error"
                                        message={errors.points_system}
                                    />
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="points_system">
                                        {t('Points system')}
                                    </Label>
                                    <Select
                                        name="points_system"
                                        value={pointsSystem}
                                        onValueChange={setPointsSystem}
                                    >
                                        <SelectTrigger
                                            id="points_system"
                                            className="w-full"
                                            aria-invalid={
                                                !!errors.points_system
                                            }
                                            aria-describedby={[
                                                errors.points_system
                                                    ? 'points_system-error'
                                                    : null,
                                                'points_system-hint',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {limits.points_system.options.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option}
                                                        value={option}
                                                    >
                                                        {pointsSystemLabel(
                                                            option,
                                                            t,
                                                        )}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        id="points_system-error"
                                        message={errors.points_system}
                                    />
                                    <p
                                        id="points_system-hint"
                                        className="text-muted-foreground text-sm"
                                    >
                                        {pointsSystemDescription(
                                            pointsSystem,
                                            t,
                                        )}
                                    </p>
                                </div>
                            )}
                            {matchFormatLocked && (
                                <p className="text-muted-foreground text-sm sm:col-span-2">
                                    {t(
                                        'The match format and points system are locked once a result has been entered. To change them, first clear all results on the Matches tab.',
                                    )}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'Changes to timing, player load and pools only affect matches that have not been scheduled yet.',
                            )}
                        </p>
                    </div>

                    <div className="flex flex-col gap-3">
                        <h3 className="text-sm font-medium">{t('Timing')}</h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="match_duration_minutes">
                                    {t('Match duration (minutes)')}
                                </Label>
                                <Input
                                    id="match_duration_minutes"
                                    name="match_duration_minutes"
                                    type="number"
                                    inputMode="numeric"
                                    min={limits.match_duration_minutes.min}
                                    max={limits.match_duration_minutes.max}
                                    required
                                    defaultValue={
                                        settings.match_duration_minutes
                                    }
                                    aria-invalid={
                                        !!errors.match_duration_minutes
                                    }
                                    aria-describedby={[
                                        errors.match_duration_minutes
                                            ? 'match_duration_minutes-error'
                                            : null,
                                        'match_duration_minutes-hint',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                />
                                <InputError
                                    id="match_duration_minutes-error"
                                    message={errors.match_duration_minutes}
                                />
                                <p
                                    id="match_duration_minutes-hint"
                                    className="text-muted-foreground text-sm"
                                >
                                    {t('Between :min and :max minutes.', {
                                        min: limits.match_duration_minutes.min,
                                        max: limits.match_duration_minutes.max,
                                    })}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="buffer_minutes">
                                    {t('Buffer between matches (minutes)')}
                                </Label>
                                <Input
                                    id="buffer_minutes"
                                    name="buffer_minutes"
                                    type="number"
                                    inputMode="numeric"
                                    min={limits.buffer_minutes.min}
                                    max={limits.buffer_minutes.max}
                                    required
                                    defaultValue={settings.buffer_minutes}
                                    aria-invalid={!!errors.buffer_minutes}
                                    aria-describedby={[
                                        errors.buffer_minutes
                                            ? 'buffer_minutes-error'
                                            : null,
                                        'buffer_minutes-hint',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                />
                                <InputError
                                    id="buffer_minutes-error"
                                    message={errors.buffer_minutes}
                                />
                                <p
                                    id="buffer_minutes-hint"
                                    className="text-muted-foreground text-sm"
                                >
                                    {t(
                                        'Time between two matches on the same field.',
                                    )}{' '}
                                    {t('Between :min and :max minutes.', {
                                        min: limits.buffer_minutes.min,
                                        max: limits.buffer_minutes.max,
                                    })}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="break_duration_minutes">
                                    {t('Break duration (minutes)')}
                                </Label>
                                <Input
                                    id="break_duration_minutes"
                                    name="break_duration_minutes"
                                    type="number"
                                    inputMode="numeric"
                                    min={limits.break_duration_minutes.min}
                                    max={limits.break_duration_minutes.max}
                                    required
                                    defaultValue={
                                        settings.break_duration_minutes
                                    }
                                    aria-invalid={
                                        !!errors.break_duration_minutes
                                    }
                                    aria-describedby={[
                                        errors.break_duration_minutes
                                            ? 'break_duration_minutes-error'
                                            : null,
                                        'break_duration_minutes-hint',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                />
                                <InputError
                                    id="break_duration_minutes-error"
                                    message={errors.break_duration_minutes}
                                />
                                <p
                                    id="break_duration_minutes-hint"
                                    className="text-muted-foreground text-sm"
                                >
                                    {t(
                                        'The length of the break during a match day.',
                                    )}{' '}
                                    {t('Between :min and :max minutes.', {
                                        min: limits.break_duration_minutes.min,
                                        max: limits.break_duration_minutes.max,
                                    })}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-3">
                        <h3 className="text-sm font-medium">
                            {t('Player load')}
                        </h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="min_rest_minutes">
                                    {t('Minimum rest per player (minutes)')}
                                </Label>
                                <Input
                                    id="min_rest_minutes"
                                    name="min_rest_minutes"
                                    type="number"
                                    inputMode="numeric"
                                    min={limits.min_rest_minutes.min}
                                    max={limits.min_rest_minutes.max}
                                    required
                                    defaultValue={settings.min_rest_minutes}
                                    aria-invalid={!!errors.min_rest_minutes}
                                    aria-describedby={[
                                        errors.min_rest_minutes
                                            ? 'min_rest_minutes-error'
                                            : null,
                                        'min_rest_minutes-hint',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                />
                                <InputError
                                    id="min_rest_minutes-error"
                                    message={errors.min_rest_minutes}
                                />
                                <p
                                    id="min_rest_minutes-hint"
                                    className="text-muted-foreground text-sm"
                                >
                                    {t(
                                        'The minimum time a player gets between two of their own matches.',
                                    )}{' '}
                                    {t('Between :min and :max minutes.', {
                                        min: limits.min_rest_minutes.min,
                                        max: limits.min_rest_minutes.max,
                                    })}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="max_matches_per_player_per_day">
                                    {t('Maximum matches per player per day')}
                                </Label>
                                <Input
                                    id="max_matches_per_player_per_day"
                                    name="max_matches_per_player_per_day"
                                    type="number"
                                    inputMode="numeric"
                                    min={
                                        limits.max_matches_per_player_per_day
                                            .min
                                    }
                                    max={
                                        limits.max_matches_per_player_per_day
                                            .max
                                    }
                                    required
                                    defaultValue={
                                        settings.max_matches_per_player_per_day
                                    }
                                    aria-invalid={
                                        !!errors.max_matches_per_player_per_day
                                    }
                                    aria-describedby={[
                                        errors.max_matches_per_player_per_day
                                            ? 'max_matches_per_player_per_day-error'
                                            : null,
                                        'max_matches_per_player_per_day-hint',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                />
                                <InputError
                                    id="max_matches_per_player_per_day-error"
                                    message={
                                        errors.max_matches_per_player_per_day
                                    }
                                />
                                <p
                                    id="max_matches_per_player_per_day-hint"
                                    className="text-muted-foreground text-sm"
                                >
                                    {t('Between :min and :max.', {
                                        min: limits
                                            .max_matches_per_player_per_day.min,
                                        max: limits
                                            .max_matches_per_player_per_day.max,
                                    })}{' '}
                                    {t('0 means unlimited.')}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-3">
                        <h3 className="text-sm font-medium">{t('Pools')}</h3>

                        <div className="grid gap-2">
                            <input
                                type="hidden"
                                name="use_pools"
                                value={usePools ? '1' : '0'}
                            />
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="use_pools"
                                    checked={usePools}
                                    onCheckedChange={(value) =>
                                        setUsePools(value === true)
                                    }
                                    aria-invalid={!!errors.use_pools}
                                    aria-controls="pool_size"
                                    aria-describedby={
                                        errors.use_pools
                                            ? 'use_pools-error'
                                            : undefined
                                    }
                                />
                                <Label htmlFor="use_pools">
                                    {t('Use pools')}
                                </Label>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Divide participants into pools instead of one schedule for everyone.',
                                )}
                            </p>
                            <InputError
                                id="use_pools-error"
                                message={errors.use_pools}
                            />

                            {usePools && (
                                <div className="mt-4 grid gap-2">
                                    <Label htmlFor="pool_size">
                                        {t('Pool size')}
                                    </Label>
                                    <Input
                                        id="pool_size"
                                        name="pool_size"
                                        type="number"
                                        inputMode="numeric"
                                        min={limits.pool_size.min}
                                        max={limits.pool_size.max}
                                        required
                                        value={poolSize}
                                        onChange={(event) =>
                                            setPoolSize(event.target.value)
                                        }
                                        aria-invalid={!!errors.pool_size}
                                        aria-describedby={[
                                            errors.pool_size
                                                ? 'pool_size-error'
                                                : null,
                                            'pool_size-hint',
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                    />
                                    <InputError
                                        id="pool_size-error"
                                        message={errors.pool_size}
                                    />
                                    <p
                                        id="pool_size-hint"
                                        className="text-muted-foreground text-sm"
                                    >
                                        {t('Between :min and :max.', {
                                            min: limits.pool_size.min,
                                            max: limits.pool_size.max,
                                        })}{' '}
                                        {t(
                                            'Each pool gets this number of players.',
                                        )}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t('Save changes')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
