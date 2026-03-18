import { useState, useEffect } from "react";
import axios from "axios";

interface BalanceState {
    balance: number | null;
    loading: boolean;
    refetch: () => void;
}

export function useBalance(accountId: string | number | null | undefined): BalanceState {
    const [balance, setBalance] = useState<number | null>(null);
    const [loading, setLoading] = useState(false);

    const fetchBalance = async () => {
        if (!accountId) {
            setBalance(null);
            return;
        }
        setLoading(true);
        try {
            const res = await axios.get(`/binance-accounts/${accountId}/balance`);
            const d = res.data?.data ?? res.data;
            setBalance(d?.total_usdt ?? d?.available_usdt ?? 0);
        } catch {
            setBalance(0);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchBalance();
    }, [accountId]);

    return { balance, loading, refetch: fetchBalance };
}
