import { Head } from '@inertiajs/react';
import { CalendarDays, MapPin, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/hooks/use-translations';
import { competitionStatusLabel } from '@/lib/competition-status';

type Props = {
    competition: {
        name: string;
        slug: string;
        status: string;
        description: string | null;
        location: string | null;
        starts_at: string;
        ends_at: string | null;
    };
    participants: Array<{ id: number; name: string }>;
};

export default function ParticipantDashboard({
    competition,
    participants,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={competition.name} />

            <div className="flex flex-col gap-4">
                <section className="flex flex-col gap-2 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-lg font-semibold sm:text-xl">
                            {competition.name}
                        </h1>
                        <Badge variant="secondary">
                            {competitionStatusLabel(competition.status, t)}
                        </Badge>
                    </div>

                    <p className="text-muted-foreground flex items-center gap-2 text-sm">
                        <CalendarDays className="size-4 shrink-0" />
                        {competition.ends_at
                            ? t(':from until :until', {
                                  from: competition.starts_at,
                                  until: competition.ends_at,
                              })
                            : competition.starts_at}
                    </p>

                    {competition.location && (
                        <p className="text-muted-foreground flex items-center gap-2 text-sm">
                            <MapPin className="size-4 shrink-0" />
                            {competition.location}
                        </p>
                    )}

                    {competition.description && (
                        <p className="mt-2 text-sm leading-relaxed">
                            {competition.description}
                        </p>
                    )}
                </section>

                <section className="flex flex-col gap-3 rounded-xl border p-4">
                    <h2 className="flex items-center gap-2 font-semibold">
                        <Users className="size-4" />
                        {t('Participants')}
                        <span className="text-muted-foreground text-sm font-normal">
                            ({participants.length})
                        </span>
                    </h2>
                    {participants.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {t('No participants yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {participants.map((participant) => (
                                <li
                                    key={participant.id}
                                    className="py-2 text-sm"
                                >
                                    {participant.name}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
