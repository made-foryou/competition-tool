import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import PasswordController from '@/actions/App/Http/Controllers/Participant/Settings/PasswordController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    competition: { name: string; slug: string };
    passwordRules: string;
};

export default function ParticipantPassword({
    competition,
    passwordRules,
}: Props) {
    const { t } = useTranslations();
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title={t('Password')} />

            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-lg font-semibold sm:text-xl">
                        {t('Password')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Ensure your account is using a long, random password to stay secure',
                        )}
                    </p>
                </div>

                <Form
                    {...PasswordController.update.form({
                        competition: competition.slug,
                    })}
                    options={{ preserveScroll: true }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="flex flex-col gap-4 rounded-xl border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    {t('Current password')}
                                </Label>
                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    autoComplete="current-password"
                                    placeholder={t('Current password')}
                                />
                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('New password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    autoComplete="new-password"
                                    placeholder={t('New password')}
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {t('Confirm password')}
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    placeholder={t('Confirm password')}
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                disabled={processing}
                                className="w-full sm:w-auto sm:self-start"
                                data-test="update-password-button"
                            >
                                {t('Save password')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
