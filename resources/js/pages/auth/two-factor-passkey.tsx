import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ConsoleHeading from '@/components/console/console-heading';
import ConsolePasskeyButton from '@/components/console/console-passkey-button';
import { useTranslations } from '@/hooks/use-translations';
import { login } from '@/routes';

export default function TwoFactorPasskey() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('Two-factor verification')} />

            <ConsoleHeading
                title={t('Two-factor verification')}
                typewriter={t('> second factor required')}
                className="mb-[18px]"
            />

            <p
                className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                style={{ '--made-delay': '0.82s' }}
            >
                {t('Confirm your identity with the passkey on this device.')}
            </p>

            <div className="made-anim" style={{ '--made-delay': '0.94s' }}>
                <ConsolePasskeyButton
                    label={t('Continue with passkey')}
                    loadingLabel={t('Verifying…')}
                />
            </div>

            <div
                className="made-anim mt-[22px] flex justify-center"
                style={{ '--made-delay': '1.06s' }}
            >
                <Link
                    href={login()}
                    className="text-console-text/70 hover:text-console-text inline-flex items-center gap-[7px] text-[13.5px] transition-colors"
                >
                    <ArrowLeft size={15} strokeWidth={1.8} />
                    {t('Back to login')}
                </Link>
            </div>
        </>
    );
}

TwoFactorPasskey.layout = { label: '2fa' };
