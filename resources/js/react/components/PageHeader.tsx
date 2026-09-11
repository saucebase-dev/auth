import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface PageHeaderProps {
    title: string;
    backUrl?: string;
}

export default function PageHeader({ title, backUrl }: PageHeaderProps) {
    return (
        <h1 className="mt-2 text-2xl font-bold">
            {backUrl && (
                <Link href={backUrl}>
                    <ArrowLeft className="mr-2 mb-1 inline size-6" />
                </Link>
            )}
            {title}
        </h1>
    );
}
