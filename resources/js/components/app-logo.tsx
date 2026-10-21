export default function AppLogo() {
    return (
        <>
            <img
                src="/assets/logo-simgroup-mark.png"
                alt=""
                aria-hidden="true"
                width={32}
                height={32}
                className="hidden size-8 shrink-0 object-contain group-data-[collapsible=icon]:block"
            />
            <div className="flex min-w-0 items-center gap-2.5 group-data-[collapsible=icon]:hidden">
                <img
                    src="/assets/logo-simgroup.png"
                    alt="SIMGROUP"
                    width={150}
                    height={39}
                    className="h-7 w-auto shrink-0"
                />
                <span className="rounded-md bg-primary/8 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-primary">
                    QCC
                </span>
            </div>
        </>
    );
}
