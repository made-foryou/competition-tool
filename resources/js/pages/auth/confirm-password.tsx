import { Form, Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import ConsoleButton from '@/components/console/console-button';
import ConsoleError from '@/components/console/console-error';
import ConsoleHeading from '@/components/console/console-heading';
import ConsoleLabel from '@/components/console/console-label';
import ConsolePasskeyButton from '@/components/console/console-passkey-button';
import ConsolePasswordInput from '@/components/console/console-password-input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('Confirm your password')} />

            <ConsoleHeading
                title={t('Confirm your password')}
                typewriter={t('> confirmation required')}
                className="mb-[18px]"
            />

            <p
                className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                style={{ '--made-delay': '0.82s' }}
            >
                {t(
                    'This is a secure area of the application. Please confirm your password before continuing.',
                )}
            </p>

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <>
                        <div
                            className="made-anim mb-[22px] flex flex-col gap-[7px]"
                            style={{ '--made-delay': '0.94s' }}
                        >
                            <ConsoleLabel htmlFor="password">
                                {t('Password')}
                            </ConsoleLabel>
                            <ConsolePasswordInput
                                id="password"
                                name="password"
                                placeholder="••••••••"
                                autoComplete="current-password"
                                autoFocus
                                required
                            />
                            <ConsoleError message={errors.password} />
                        </div>

                        <div
                            className="made-anim"
                            style={{ '--made-delay': '1.06s' }}
                        >
                            <ConsoleButton
                                type="submit"
                                variant="cta"
                                size="lg"
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                {processing ? t('Confirming…') : t('Confirm')}
                                {!processing && (
                                    <ArrowRight size={17} strokeWidth={1.8} />
                                )}
                            </ConsoleButton>
                        </div>
                    </>
                )}
            </Form>

            <div className="made-anim mt-5" style={{ '--made-delay': '1.18s' }}>
                <div className="mb-5 flex items-center gap-3">
                    <span className="bg-console-border h-px flex-1" />
                    <span className="text-console-text/40 font-mono text-[11px] uppercase">
                        {t('or')}
                    </span>
                    <span className="bg-console-border h-px flex-1" />
                </div>
                <ConsolePasskeyButton
                    routes={{
                        options: confirmOptions(),
                        submit: confirmStore(),
                    }}
                    label={t('Continue with passkey')}
                    loadingLabel={t('Confirming…')}
                />
            </div>
        </>
    );
}

ConfirmPassword.layout = { label: 'bevestiging' };
