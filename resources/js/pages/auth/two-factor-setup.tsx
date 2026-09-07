import { Form, Head, Link, router } from '@inertiajs/react';
import { usePasskeyRegister } from '@laravel/passkeys/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { ArrowRight, Check, Copy, KeyRound, ScanLine } from 'lucide-react';
import { useEffect, useState } from 'react';
import ConsoleButton from '@/components/console/console-button';
import ConsoleError from '@/components/console/console-error';
import ConsoleHeading from '@/components/console/console-heading';
import ConsoleInput from '@/components/console/console-input';
import ConsoleLabel from '@/components/console/console-label';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH, useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { useTranslations } from '@/hooks/use-translations';
import { dashboard, logout } from '@/routes';
import { confirm, enable } from '@/routes/two-factor';

type Step = 'choice' | 'totp' | 'totp-confirm' | 'recovery' | 'passkey';

type Props = {
    requiresConfirmation: boolean;
};

function defaultPasskeyName(): string {
    const ua = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    return [browser, os].filter(Boolean).join(' · ') || 'Passkey';
}

const otpSlotClassName =
    'border-console-input-border bg-console-surface text-console-text data-[active=true]:border-copper data-[active=true]:ring-copper/30 size-11 text-[17px]';

export default function TwoFactorSetup({ requiresConfirmation }: Props) {
    const { t } = useTranslations();
    const [step, setStep] = useState<Step>('choice');
    const [otpCode, setOtpCode] = useState('');
    const [copiedText, copy] = useClipboard();
    const {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        errors: setupErrors,
        fetchSetupData,
        fetchRecoveryCodes,
    } = useTwoFactorAuth();

    const [passkeyName, setPasskeyName] = useState(defaultPasskeyName);
    const {
        register,
        isLoading: passkeyLoading,
        error: passkeyError,
        isSupported: passkeySupported,
    } = usePasskeyRegister({
        onSuccess: () => router.visit(dashboard()),
    });

    useEffect(() => {
        if (step === 'recovery' && recoveryCodesList.length === 0) {
            void fetchRecoveryCodes();
        }
    }, [step, recoveryCodesList.length, fetchRecoveryCodes]);

    const CopyIcon = copiedText === manualSetupKey ? Check : Copy;

    return (
        <>
            <Head title={t('Secure your account')} />

            <ConsoleHeading
                title={t('Secure your account')}
                typewriter={t('> 2FA required')}
                className="mb-[18px]"
            />

            {step === 'choice' && (
                <>
                    <p
                        className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                        style={{ '--made-delay': '0.82s' }}
                    >
                        {t(
                            'A second factor is required for access to the console. Choose how you want to secure your account.',
                        )}
                    </p>

                    <div
                        className="made-anim flex flex-col gap-3"
                        style={{ '--made-delay': '0.94s' }}
                    >
                        <Form
                            {...enable.form()}
                            onSuccess={() => {
                                setStep('totp');
                                void fetchSetupData();
                            }}
                        >
                            {({ processing }) => (
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="border-console-input-border rounded-console-lg hover:border-copper hover:bg-console-surface flex w-full cursor-pointer items-start gap-3.5 border p-4 text-left transition-colors"
                                >
                                    <span className="text-copper mt-0.5">
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <ScanLine size={20} />
                                        )}
                                    </span>
                                    <span>
                                        <span className="text-console-text block text-[15px] font-medium">
                                            {t('Authenticator app')}
                                        </span>
                                        <span className="text-console-text/55 mt-0.5 block text-[13px] leading-snug">
                                            {t(
                                                'Scan the QR code with an app like 1Password or Google Authenticator.',
                                            )}
                                        </span>
                                    </span>
                                </button>
                            )}
                        </Form>

                        {passkeySupported && (
                            <button
                                type="button"
                                onClick={() => setStep('passkey')}
                                className="border-console-input-border rounded-console-lg hover:border-copper hover:bg-console-surface flex w-full cursor-pointer items-start gap-3.5 border p-4 text-left transition-colors"
                            >
                                <span className="text-copper mt-0.5">
                                    <KeyRound size={20} />
                                </span>
                                <span>
                                    <span className="text-console-text block text-[15px] font-medium">
                                        {t('Passkey')}
                                    </span>
                                    <span className="text-console-text/55 mt-0.5 block text-[13px] leading-snug">
                                        {t(
                                            'Use Face ID, Touch ID or a security key on this device.',
                                        )}
                                    </span>
                                </span>
                            </button>
                        )}
                    </div>
                </>
            )}

            {step === 'totp' && (
                <div className="made-pop">
                    <p className="text-console-text/65 mb-4 text-sm leading-relaxed">
                        {t('Scan the QR code')}
                    </p>

                    <div className="mx-auto mb-4 w-52 rounded-lg bg-white p-3 [&_svg]:size-full">
                        {qrCodeSvg ? (
                            <div
                                className="aspect-square w-full"
                                dangerouslySetInnerHTML={{ __html: qrCodeSvg }}
                            />
                        ) : (
                            <div className="flex aspect-square items-center justify-center text-black">
                                <Spinner />
                            </div>
                        )}
                    </div>

                    <p className="text-console-text/55 mb-2 text-[13px]">
                        {t('Or enter this code manually:')}
                    </p>
                    <div className="border-console-input-border rounded-console-sm mb-5 flex items-stretch overflow-hidden border">
                        {manualSetupKey ? (
                            <>
                                <input
                                    type="text"
                                    readOnly
                                    value={manualSetupKey}
                                    className="text-console-text w-full bg-transparent p-2.5 font-mono text-[12.5px] outline-none"
                                />
                                <button
                                    type="button"
                                    onClick={() => copy(manualSetupKey)}
                                    className="border-console-input-border text-console-text/60 hover:text-console-text border-l px-3"
                                >
                                    <CopyIcon size={14} />
                                </button>
                            </>
                        ) : (
                            <div className="flex w-full items-center justify-center p-3">
                                <Spinner />
                            </div>
                        )}
                    </div>

                    {setupErrors.length > 0 && (
                        <ConsoleError message={setupErrors.join(' ')} />
                    )}

                    <ConsoleButton
                        variant="cta"
                        size="lg"
                        className="w-full"
                        onClick={() => {
                            if (requiresConfirmation) {
                                setStep('totp-confirm');
                            } else {
                                setStep('recovery');
                            }
                        }}
                    >
                        {t('Continue')}
                        <ArrowRight size={17} strokeWidth={1.8} />
                    </ConsoleButton>
                </div>
            )}

            {step === 'totp-confirm' && (
                <div className="made-pop">
                    <p className="text-console-text/65 mb-5 text-sm leading-relaxed">
                        {t('Enter the 6-digit code from your app to confirm.')}
                    </p>

                    <Form
                        {...confirm.form()}
                        resetOnError
                        onSuccess={() => setStep('recovery')}
                    >
                        {({
                            processing,
                            errors,
                        }: {
                            processing: boolean;
                            errors?: {
                                confirmTwoFactorAuthentication?: {
                                    code?: string;
                                };
                            };
                        }) => (
                            <>
                                <div className="mb-5 flex flex-col items-center gap-3">
                                    <InputOTP
                                        name="code"
                                        maxLength={OTP_MAX_LENGTH}
                                        value={otpCode}
                                        onChange={setOtpCode}
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
                                    <ConsoleError
                                        message={
                                            errors
                                                ?.confirmTwoFactorAuthentication
                                                ?.code
                                        }
                                    />
                                </div>

                                <div className="flex gap-3">
                                    <ConsoleButton
                                        variant="secondary"
                                        size="md"
                                        className="flex-1"
                                        onClick={() => setStep('totp')}
                                        disabled={processing}
                                    >
                                        {t('Back')}
                                    </ConsoleButton>
                                    <ConsoleButton
                                        type="submit"
                                        variant="cta"
                                        size="md"
                                        className="flex-1"
                                        disabled={
                                            processing ||
                                            otpCode.length < OTP_MAX_LENGTH
                                        }
                                    >
                                        {processing && <Spinner />}
                                        {t('Confirm')}
                                    </ConsoleButton>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            )}

            {step === 'recovery' && (
                <div className="made-pop">
                    <p className="text-console-success mb-3 text-sm font-medium">
                        {t('Two-factor authentication is enabled.')}
                    </p>
                    <p className="text-console-text/65 mb-4 text-sm leading-relaxed">
                        {t('Recovery codes')} —{' '}
                        {t(
                            'Store these codes somewhere safe. Each code can be used once if you lose access to your authenticator.',
                        )}
                    </p>

                    <div className="border-console-input-border rounded-console-sm mb-5 grid grid-cols-2 gap-x-4 gap-y-1.5 border p-4">
                        {recoveryCodesList.length > 0 ? (
                            recoveryCodesList.map((code) => (
                                <span
                                    key={code}
                                    className="text-console-text font-mono text-[12.5px]"
                                >
                                    {code}
                                </span>
                            ))
                        ) : (
                            <span className="col-span-2 flex justify-center">
                                <Spinner />
                            </span>
                        )}
                    </div>

                    <ConsoleButton
                        variant="cta"
                        size="lg"
                        className="w-full"
                        onClick={() => router.visit(dashboard())}
                    >
                        {t("I've saved my recovery codes")}
                        <ArrowRight size={17} strokeWidth={1.8} />
                    </ConsoleButton>
                </div>
            )}

            {step === 'passkey' && (
                <div className="made-pop">
                    <p className="text-console-text/65 mb-5 text-sm leading-relaxed">
                        {t(
                            'Use Face ID, Touch ID or a security key on this device.',
                        )}
                    </p>

                    <div className="mb-5 flex flex-col gap-[7px]">
                        <ConsoleLabel htmlFor="passkey-name">
                            {t('Name')}
                        </ConsoleLabel>
                        <ConsoleInput
                            id="passkey-name"
                            type="text"
                            value={passkeyName}
                            onChange={(event) =>
                                setPasskeyName(event.target.value)
                            }
                            autoFocus
                        />
                        <ConsoleError message={passkeyError ?? undefined} />
                    </div>

                    <div className="flex gap-3">
                        <ConsoleButton
                            variant="secondary"
                            size="md"
                            className="flex-1"
                            onClick={() => setStep('choice')}
                            disabled={passkeyLoading}
                        >
                            {t('Back')}
                        </ConsoleButton>
                        <ConsoleButton
                            variant="cta"
                            size="md"
                            className="flex-1"
                            onClick={() => void register(passkeyName)}
                            disabled={passkeyLoading || !passkeyName.trim()}
                        >
                            {passkeyLoading ? (
                                <Spinner />
                            ) : (
                                <KeyRound size={16} />
                            )}
                            {t('Add passkey')}
                        </ConsoleButton>
                    </div>
                </div>
            )}

            <div className="mt-[22px] flex justify-center">
                <Link
                    href={logout()}
                    className="text-console-text/70 hover:text-console-text text-[13.5px] transition-colors"
                >
                    {t('Log out')}
                </Link>
            </div>
        </>
    );
}

TwoFactorSetup.layout = { label: '2fa-setup' };
