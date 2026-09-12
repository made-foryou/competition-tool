import { Head } from '@inertiajs/react';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import { useTranslations } from '@/hooks/use-translations';

// oxfmt-ignore
type Props = {
    competition: { name: string; slug: string };
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function ParticipantSecurity(props: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('Security')} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-lg font-semibold sm:text-xl">
                        {t('Security')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('Extra security with an app or passkey')}
                    </p>
                </div>

                <section className="rounded-xl border p-4">
                    <ManageTwoFactor
                        canManageTwoFactor={props.canManageTwoFactor}
                        requiresConfirmation={props.requiresConfirmation}
                        twoFactorEnabled={props.twoFactorEnabled}
                    />
                </section>

                <section className="rounded-xl border p-4">
                    <ManagePasskeys
                        canManagePasskeys={props.canManagePasskeys}
                        passkeys={props.passkeys}
                    />
                </section>
            </div>
        </>
    );
}
