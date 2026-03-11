import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import { SUPPORTED_PAIRS } from "@/utils/constants";
import { Check } from "lucide-react";
import { useState } from "react";

interface PairSelectorProps {
    value: string;
    onValueChange: (pair: string) => void;
    isFutures?: boolean;
    children: React.ReactNode;
    align?: "start" | "center" | "end";
    sideOffset?: number;
    className?: string;
}

function getCoinIcon(symbol: string) {
    const base = symbol.replace("USDT", "").toLowerCase();
    return `https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/32/color/${base}.png`;
}

export default function PairSelector({
    value,
    onValueChange,
    isFutures = true,
    children,
    align = "start",
    sideOffset = 4,
    className,
}: PairSelectorProps) {
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>{children}</PopoverTrigger>
            <PopoverContent
                className={cn("w-[240px] p-0", className)}
                align={align}
                sideOffset={sideOffset}
            >
                <Command>
                    <CommandInput placeholder="Buscar par..." className="h-9" />
                    <CommandList>
                        <CommandEmpty>No se encontró el par.</CommandEmpty>
                        <CommandGroup>
                            {SUPPORTED_PAIRS.map((pair) => {
                                const base = pair.replace("USDT", "");
                                const display = `${base}/USDT${isFutures ? " Perp" : ""}`;
                                return (
                                    <CommandItem
                                        key={pair}
                                        value={pair}
                                        onSelect={() => {
                                            onValueChange(pair);
                                            setOpen(false);
                                        }}
                                        className="flex items-center gap-2 text-xs py-2"
                                    >
                                        <img
                                            src={getCoinIcon(pair)}
                                            alt={base}
                                            className="w-4 h-4 rounded-full shrink-0"
                                            onError={(e) => {
                                                (e.target as HTMLImageElement).style.display = "none";
                                            }}
                                        />
                                        <span className="font-medium">{display}</span>
                                        <Check
                                            className={cn(
                                                "ml-auto h-3.5 w-3.5",
                                                value === pair ? "opacity-100" : "opacity-0",
                                            )}
                                        />
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
