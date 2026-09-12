import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import CompetitionController from '@/actions/App/Http/Controllers/CompetitionController';
import type { AvailabilityRow } from '@/components/competitions/availability-matrix';
import AvailabilityMatrix from '@/components/competitions/availability-matrix';
import type { CompetitionProps } from '@/components/competitions/competition-form';
import CompetitionForm from '@/components/competitions/competition-form';
import type { MatchDayListItem } from '@/components/competitions/match-day-manager';
import MatchDayManager from '@/components/competitions/match-day-manager';
import type {
    ParticipantProps,
    PendingInvitationProps,
} from '@/components/competitions/participant-manager';
import ParticipantManager from '@/components/competitions/participant-manager';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslations } from '@/hooks/use-translations';
import { competitionStatusLabel } from '@/lib/competition-status';
import { edit, index, update } from '@/routes/competitions';

type Props = {
    competition: CompetitionProps;
    participants: ParticipantProps[];
    matchDays: MatchDayListItem[];
    availability: AvailabilityRow[];
    pendingInvitations: PendingInvitationProps[];
};

const TABS = ['general', 'match-days', 'participants', 'availability'] as const;

type TabValue = (typeof TABS)[number];

const DEFAULT_TAB: TabValue = 'general';

/**
 * Leest de actieve tab uit de querystring van een Inertia-url. Onbekende of
 * ontbrekende waarden vallen terug op de standaardtab.
 */
function resolveTab(url: string): TabValue {
    const [, query = ''] = url.split('?');
    const tab = new URLSearchParams(query).get('tab');

    return TABS.includes(tab as TabValue) ? (tab as TabValue) : DEFAULT_TAB;
}

/**
 * Bouwt dezelfde url met `?tab=` voor de gegeven tab. De standaardtab laat de
 * querystring weg, zodat de schone url naar "General" blijft verwijzen.
 */
function urlForTab(url: string, tab: TabValue): string {
    const [path, query = ''] = url.split('?');
    const params = new URLSearchParams(query);

    if (tab === DEFAULT_TAB) {
        params.delete('tab');
    } else {
        params.set('tab', tab);
    }

    const search = params.toString();

    return search === '' ? path : `${path}?${search}`;
}

export default function CompetitionsEdit({
    competition,
    participants,
    matchDays,
    availability,
    pendingInvitations,
}: Props) {
    const { t } = useTranslations();
    const { url } = usePage();

    const [activeTab, setActiveTab] = useState<TabValue>(() => resolveTab(url));

    /**
     * Houdt de querystring gelijk aan de actieve tab met een client-side visit,
     * dus zonder serverbezoek. Dit herstelt `?tab=` ook nadat een actie binnen
     * een tab naar de schone edit-url redirect.
     */
    useEffect(() => {
        if (resolveTab(url) === activeTab) {
            return;
        }

        router.replace({
            url: urlForTab(url, activeTab),
            preserveState: true,
            preserveScroll: true,
        });
    }, [url, activeTab]);

    return (
        <>
            <Head title={competition.name} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Heading title={competition.name} className="mb-0" />
                    <Badge variant="secondary">
                        {competitionStatusLabel(competition.status, t)}
                    </Badge>
                </div>

                <Tabs
                    value={activeTab}
                    onValueChange={(value) => setActiveTab(value as TabValue)}
                    className="gap-6"
                >
                    <TabsList>
                        <TabsTrigger value="general">
                            {t('General')}
                        </TabsTrigger>
                        <TabsTrigger value="match-days">
                            {t('Match days')}
                        </TabsTrigger>
                        <TabsTrigger value="participants">
                            {t('Participants')}
                        </TabsTrigger>
                        <TabsTrigger value="availability">
                            {t('Availability')}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent
                        value="general"
                        className="flex max-w-xl flex-col gap-8"
                    >
                        <CompetitionForm
                            competition={competition}
                            action={update(competition.id).url}
                            method="put"
                            submitLabel={t('Save changes')}
                        />

                        <div className="border-destructive/20 bg-destructive/5 space-y-4 rounded-lg border p-4">
                            <div className="space-y-0.5">
                                <p className="font-medium">
                                    {t('Danger zone')}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'Deleting a competition cannot be undone. Participant accounts are kept.',
                                    )}
                                </p>
                            </div>

                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        {t('Delete competition')}
                                    </Button>
                                }
                                title={t('Delete competition?')}
                                description={t(
                                    'This removes the competition and its participant list. User accounts are kept.',
                                )}
                                action={CompetitionController.destroy.form(
                                    competition.id,
                                )}
                                confirmLabel={t('Delete competition')}
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="match-days" className="max-w-4xl">
                        <MatchDayManager
                            competitionId={competition.id}
                            matchDays={matchDays}
                        />
                    </TabsContent>

                    <TabsContent value="participants" className="max-w-4xl">
                        <ParticipantManager
                            competitionId={competition.id}
                            participants={participants}
                            pendingInvitations={pendingInvitations}
                        />
                    </TabsContent>

                    <TabsContent value="availability">
                        <AvailabilityMatrix
                            matchDays={matchDays}
                            availability={availability}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

CompetitionsEdit.layout = ({ competition }: Props) => ({
    breadcrumbs: [
        { title: 'Competitions', href: index() },
        {
            title: competition.name,
            href: edit(competition.id),
            translate: false,
        },
    ],
});
