import AppLayout from '@/Layouts/AppLayout';
import { IconUpload } from '@/components/Icons';
import { Button, Card } from '@/components/ui';
import { PageHeader } from '@/components/ui/PageHeader';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

interface ReceiptDraft {
    amount: number;
    currency: string;
    merchant: string;
    category: string;
    date: string | null;
    type: 'income' | 'expense';
}

export default function ReceiptScanner() {
    const t = useTrans();
    const { props } = usePage<SharedProps>();
    const draft = (props.flash as Record<string, unknown> | undefined)
        ?.receiptDraft as ReceiptDraft | null;

    const fileRef = useRef<HTMLInputElement>(null);
    const [scanning, setScanning] = useState(false);
    const [scanError, setScanError] = useState('');

    const submitScan = () => {
        const file = fileRef.current?.files?.[0];
        if (!file) {
            setScanError(
                t('transactions.receipt_required') ||
                    'Choose a receipt image first',
            );
            return;
        }
        setScanning(true);
        setScanError('');
        const form = new FormData();
        form.append('receipt', file);
        router.post('/receipt/scan', form, {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => {
                setScanning(false);
                setScanError(
                    e.receipt ||
                        t('transactions.receipt_error') ||
                        'Scanning failed',
                );
            },
            onFinish: () => setScanning(false),
        });
    };

    return (
        <AppLayout>
            <PageHeader
                title={t('transactions.receipt_title') || 'Receipt Scanner'}
                subtitle={
                    t('transactions.receipt_subtitle') ||
                    'Upload a receipt photo to extract a transaction draft.'
                }
            />

            <div className="max-w-2xl space-y-5">
                <Card
                    title={t('transactions.receipt_upload') || 'Upload receipt'}
                >
                    <div className="space-y-3">
                        <input
                            ref={fileRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="file:btn-soft text-ink-faint text-sm file:mr-3"
                        />
                        {scanError ? (
                            <p className="text-coral text-xs">{scanError}</p>
                        ) : null}
                        <div className="flex justify-end">
                            <Button
                                onClick={submitScan}
                                loading={scanning}
                                variant="secondary"
                            >
                                <IconUpload size={16} />
                                {t('transactions.receipt_scan') || 'Scan'}
                            </Button>
                        </div>
                    </div>
                </Card>

                {draft ? (
                    <Card
                        title={
                            t('transactions.receipt_draft') || 'Extracted draft'
                        }
                    >
                        <div className="text-ink-faint grid grid-cols-2 gap-3 text-sm">
                            <span>
                                {t('transactions.receipt_amount') || 'Amount'}
                            </span>
                            <span className="text-ink font-semibold">
                                {draft.amount.toFixed(2)} {draft.currency}
                            </span>
                            <span>
                                {t('transactions.receipt_merchant') ||
                                    'Merchant'}
                            </span>
                            <span className="text-ink">
                                {draft.merchant || '—'}
                            </span>
                            <span>
                                {t('transactions.receipt_category') ||
                                    'Category'}
                            </span>
                            <span className="text-ink">
                                {draft.category || '—'}
                            </span>
                            <span>
                                {t('transactions.receipt_date') || 'Date'}
                            </span>
                            <span className="text-ink">
                                {draft.date || '—'}
                            </span>
                            <span>
                                {t('transactions.receipt_type') || 'Type'}
                            </span>
                            <span className="text-ink">{draft.type}</span>
                        </div>
                        <p className="text-ink-faint mt-4 text-xs">
                            {t('transactions.receipt_hint') ||
                                'Review the extracted details, then add the transaction manually.'}
                        </p>
                    </Card>
                ) : null}
            </div>
        </AppLayout>
    );
}
