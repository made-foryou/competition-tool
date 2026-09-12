import { Form } from '@inertiajs/react';
import type { VariantProps } from 'class-variance-authority';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    trigger: ReactNode;
    title: string;
    description: string;
    action: RouteFormDefinition<'get' | 'post' | 'put' | 'patch' | 'delete'>;
    confirmLabel: string;
    cancelLabel?: string;
    confirmVariant?: VariantProps<typeof buttonVariants>['variant'];
};

export default function ConfirmDialog({
    trigger,
    title,
    description,
    action,
    confirmLabel,
    cancelLabel,
    confirmVariant = 'destructive',
}: Props) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent closeLabel={t('Close')}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {cancelLabel ?? t('Cancel')}
                        </Button>
                    </DialogClose>
                    <Form
                        {...action}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        onError={() => toast.error(t('Something went wrong.'))}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant={confirmVariant}
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {confirmLabel}
                            </Button>
                        )}
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
