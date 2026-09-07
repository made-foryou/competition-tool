import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import ConsoleButton from '@/components/console/console-button';
import ConsoleError from '@/components/console/console-error';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label: string;
    loadingLabel: string;
};

/**
 * Console-styled passkey verification button. Renders nothing when the
 * browser does not support WebAuthn.
 */
export default function ConsolePasskeyButton({
    routes,
    label,
    loadingLabel,
}: Props) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? dashboard().url);
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <ConsoleButton
                variant="secondary"
                size="md"
                className="w-full"
                onClick={verify}
                disabled={isLoading}
            >
                {isLoading ? <Spinner /> : <KeyRound size={16} />}
                {isLoading ? loadingLabel : label}
            </ConsoleButton>
            <ConsoleError
                message={error ?? undefined}
                className="text-center"
            />
        </div>
    );
}
