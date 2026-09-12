import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Participant/Settings/ProfileController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type Props = {
    competition: { name: string; slug: string };
};

type PageProps = {
    auth: Auth;
};

export default function ParticipantProfile({ competition }: Props) {
    const { t } = useTranslations();
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title={t('Profile')} />

            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-lg font-semibold sm:text-xl">
                        {t('Profile')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('Your name and email address')}
                    </p>
                </div>

                <Form
                    {...ProfileController.update.form({
                        competition: competition.slug,
                    })}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4 rounded-xl border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={auth.user.name}
                                    required
                                    autoComplete="name"
                                    placeholder={t('Full name')}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="nickname">
                                    {t('Nickname')}
                                </Label>
                                <Input
                                    id="nickname"
                                    name="nickname"
                                    defaultValue={auth.user.nickname ?? ''}
                                    placeholder={t('Optional')}
                                />
                                <InputError message={errors.nickname} />
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'Other participants see your nickname instead of your name.',
                                    )}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={auth.user.email}
                                    required
                                    autoComplete="username"
                                    placeholder={t('Email address')}
                                />
                                <InputError message={errors.email} />
                            </div>

                            {auth.user.email_verified_at === null && (
                                <p className="text-muted-foreground text-sm">
                                    {t('Your email address is unverified.')}{' '}
                                    <Link
                                        href={send()}
                                        as="button"
                                        className="text-foreground underline underline-offset-4"
                                    >
                                        {t(
                                            'Click here to re-send the verification email.',
                                        )}
                                    </Link>
                                </p>
                            )}

                            <Button
                                disabled={processing}
                                className="w-full sm:w-auto sm:self-start"
                                data-test="update-profile-button"
                            >
                                {t('Save')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
