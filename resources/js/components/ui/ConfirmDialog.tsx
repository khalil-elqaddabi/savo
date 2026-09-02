import { useTrans } from '@/lib/translation';
import { Button, Dialog } from './index';

interface Props {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message?: string;
    confirmLabel?: string;
    loading?: boolean;
}

export function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    message,
    confirmLabel,
    loading = false,
}: Props) {
    const t = useTrans();
    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={title}
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button
                        variant="danger"
                        loading={loading}
                        onClick={onConfirm}
                    >
                        {confirmLabel ?? t('common.delete')}
                    </Button>
                </div>
            }
        >
            {message ? (
                <p className="text-ink-soft text-sm">{message}</p>
            ) : null}
        </Dialog>
    );
}
