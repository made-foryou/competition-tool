import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import ConsoleButton from '@/components/console/console-button';
import ConsoleError from '@/components/console/console-error';
import ConsoleHeading from '@/components/console/console-heading';
import ConsoleInput from '@/components/console/console-input';
import ConsolePasskeyButton from '@/components/console/console-passkey-button';
import { Spinner } from '@/components/ui/spinner';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/two-factor/login';

type Props = {
    hasPasskeys?: boolean;
};

export default function TwoFactorChallenge({ hasPasskeys = false }: Props) {
    const { t } = useTranslations();
    const [showRecoveryInput, setShowRecoveryInput] = useState(false);
    const [code, setCode] = useState('');

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    const otpSlotClassName =
        'border-console-input-border bg-console-surface text-console-text data-[active=true]:border-copper data-[active=true]:ring-copper/30 size-11 text-[17px]';

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
                {showRecoveryInput
                    ? t('Enter one of your recovery codes.')
                    : t('Enter the 6-digit code from your authenticator app.')}
            </p>

            <Form
                {...store.form()}
                resetOnError
                resetOnSuccess={!showRecoveryInput}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        <div
                            className="made-anim mb-[22px]"
                            style={{ '--made-delay': '0.94s' }}
                        >
                            {showRecoveryInput ? (
                                <div className="flex flex-col gap-[7px]">
                                    <ConsoleInput
                                        name="recovery_code"
                                        type="text"
                                        placeholder={t('Recovery code')}
                                        autoFocus
                                        required
                                        className="font-mono"
                                    />
                                    <ConsoleError
                                        message={errors.recovery_code}
                                    />
                                </div>
                            ) : (
                                <div className="flex flex-col items-center gap-3 text-center">
                                    <InputOTP
                                        name="code"
                                        maxLength={OTP_MAX_LENGTH}
                                        value={code}
                                        onChange={(value) => setCode(value)}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                        autoFocus
                                    >
                                        <InputOTPGroup>
                                            {Array.from(
                                                { length: OTP_MAX_LENGTH },
                                                (_, index) => (
                                                    <InputOTPSlot
                                                        key={index}
                                                        index={index}
                                                        className={
                                                            otpSlotClassName
                                                        }
                                                    />
                                                ),
                                            )}
                                        </InputOTPGroup>
                                    </InputOTP>
                                    <ConsoleError message={errors.code} />
                                </div>
                            )}
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
                            >
                                {processing && <Spinner />}
                                {processing ? t('Verifying…') : t('Continue')}
                                {!processing && (
                                    <ArrowRight size={17} strokeWidth={1.8} />
                                )}
                            </ConsoleButton>
                        </div>

                        <div
                            className="made-anim text-console-text/60 mt-[22px] text-center text-[13.5px]"
                            style={{ '--made-delay': '1.18s' }}
                        >
                            <span>{t('or')} </span>
                            <button
                                type="button"
                                className="text-copper cursor-pointer hover:underline"
                                onClick={() => toggleRecoveryMode(clearErrors)}
                            >
                                {showRecoveryInput
                                    ? t('use an authentication code')
                                    : t('use a recovery code')}
                            </button>
                        </div>
                    </>
                )}
            </Form>

            {hasPasskeys && (
                <div
                    className="made-anim mt-5"
                    style={{ '--made-delay': '1.24s' }}
                >
                    <div className="mb-5 flex items-center gap-3">
                        <span className="bg-console-border h-px flex-1" />
                        <span className="text-console-text/40 font-mono text-[11px] uppercase">
                            {t('or')}
                        </span>
                        <span className="bg-console-border h-px flex-1" />
                    </div>
                    <ConsolePasskeyButton
                        label={t('Continue with passkey')}
                        loadingLabel={t('Verifying…')}
                    />
                </div>
            )}
        </>
    );
}

TwoFactorChallenge.layout = { label: '2fa' };
