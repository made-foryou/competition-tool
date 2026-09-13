import { Swords } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { matchStatusLabel } from '@/lib/match-status';
import { pluralize } from '@/lib/plural';

export type MatchProps = {
    id: number;
    first_player: string;
    second_player: string;
    status: string;
};

type Props = {
    matches: MatchProps[];
};

export default function MatchList({ matches }: Props) {
    const { t } = useTranslations();

    if (matches.length === 0) {
        return (
            <EmptyState
                icon={Swords}
                title={t('No matches yet')}
                description={t(
                    'Matches appear automatically once the competition has at least two participants.',
                )}
            />
        );
    }

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-muted-foreground text-sm">
                    {t(
                        'The match list updates automatically when participants join or leave.',
                    )}
                </p>
                <Badge variant="secondary">
                    {pluralize(
                        t,
                        matches.length,
                        ':count match',
                        ':count matches',
                    )}
                </Badge>
            </div>

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col" className="p-3">
                                {t('Player 1')}
                            </TableHead>
                            <TableHead scope="col" className="p-3">
                                {t('Player 2')}
                            </TableHead>
                            <TableHead scope="col" className="p-3">
                                {t('Status')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {matches.map((match) => (
                            <TableRow key={match.id}>
                                <TableCell className="p-3">
                                    {match.first_player}
                                </TableCell>
                                <TableCell className="p-3">
                                    {match.second_player}
                                </TableCell>
                                <TableCell className="p-3">
                                    <Badge variant="secondary">
                                        {matchStatusLabel(match.status, t)}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </section>
    );
}
