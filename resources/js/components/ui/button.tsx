import { Button as ButtonPrimitive } from "@base-ui/react/button"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "group/button inline-flex shrink-0 items-center justify-center rounded-lg border border-transparent bg-clip-padding text-sm font-medium whitespace-nowrap transition-all outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 active:not-aria-[haspopup]:translate-y-px disabled:pointer-events-none disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20   [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground hover:bg-primary/80",
        outline:
          "border-primary/30 bg-primary/15 text-primary hover:bg-primary/25 aria-expanded:bg-primary/25 aria-expanded:text-primary",
        secondary:
          "border-slate-400/40 bg-slate-200 text-slate-900 hover:bg-slate-300 aria-expanded:bg-slate-300 aria-expanded:text-slate-900 dark:bg-slate-700 dark:text-slate-50 dark:hover:bg-slate-600",
        ghost:
          "bg-violet/15 text-violet hover:bg-violet/25 aria-expanded:bg-violet/25 aria-expanded:text-violet",
        destructive:
          "bg-destructive text-destructive-foreground hover:bg-destructive/90 focus-visible:border-destructive/40 focus-visible:ring-destructive/20",
        warning:
          "border-warning/20 bg-warning/10 text-warning hover:bg-warning/15 focus-visible:border-warning/40 focus-visible:ring-warning/20",
        info:
          "border-info/20 bg-info/10 text-info hover:bg-info/15 focus-visible:border-info/40 focus-visible:ring-info/20",
        violet:
          "border-violet/20 bg-violet/10 text-violet hover:bg-violet/15 focus-visible:border-violet/40 focus-visible:ring-violet/20",
        teal:
          "border-teal/20 bg-teal/10 text-teal hover:bg-teal/15 focus-visible:border-teal/40 focus-visible:ring-teal/20",
        link: "bg-teal/15 text-teal underline-offset-4 hover:bg-teal/25 hover:underline",
      },
      size: {
        default:
          "h-8 gap-1.5 px-2.5 has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2",
        xs: "h-6 gap-1 rounded-[min(var(--radius-md),10px)] px-2 text-xs in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3",
        sm: "h-7 gap-1 rounded-[min(var(--radius-md),12px)] px-2.5 text-[0.8rem] in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3.5",
        lg: "h-9 gap-1.5 px-2.5 has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2",
        icon: "size-8",
        "icon-xs":
          "size-6 rounded-[min(var(--radius-md),10px)] in-data-[slot=button-group]:rounded-lg [&_svg:not([class*='size-'])]:size-3",
        "icon-sm":
          "size-7 rounded-[min(var(--radius-md),12px)] in-data-[slot=button-group]:rounded-lg",
        "icon-lg": "size-9",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant = "default",
  size = "default",
  ...props
}: ButtonPrimitive.Props & VariantProps<typeof buttonVariants>) {
  return (
    <ButtonPrimitive
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
