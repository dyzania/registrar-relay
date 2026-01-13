import { useEffect, useState } from 'react';
import { supabase } from '@/integrations/supabase/client';
import type { QueueItem, Window, TransactionType } from '@/types/queue';

export function useQueue() {
  const [queue, setQueue] = useState<QueueItem[]>([]);
  const [windows, setWindows] = useState<Window[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchQueue = async () => {
    const { data, error } = await supabase
      .from('queue')
      .select('*')
      .order('queue_number', { ascending: true });
    
    if (!error && data) {
      setQueue(data as QueueItem[]);
    }
  };

  const fetchWindows = async () => {
    const { data, error } = await supabase
      .from('windows')
      .select('*')
      .order('window_number', { ascending: true });
    
    if (!error && data) {
      setWindows(data as Window[]);
    }
  };

  useEffect(() => {
    const init = async () => {
      await Promise.all([fetchQueue(), fetchWindows()]);
      setLoading(false);
    };
    init();

    // Subscribe to realtime updates
    const queueChannel = supabase
      .channel('queue-changes')
      .on(
        'postgres_changes',
        { event: '*', schema: 'public', table: 'queue' },
        () => {
          fetchQueue();
        }
      )
      .subscribe();

    const windowsChannel = supabase
      .channel('windows-changes')
      .on(
        'postgres_changes',
        { event: '*', schema: 'public', table: 'windows' },
        () => {
          fetchWindows();
        }
      )
      .subscribe();

    return () => {
      supabase.removeChannel(queueChannel);
      supabase.removeChannel(windowsChannel);
    };
  }, []);

  const createQueueItem = async (
    studentName: string,
    transactionType: TransactionType,
    studentId?: string
  ) => {
    // Get next queue number
    const { data: numberData, error: numberError } = await supabase
      .rpc('get_next_queue_number');

    if (numberError) throw numberError;

    const { data, error } = await supabase
      .from('queue')
      .insert({
        queue_number: numberData,
        student_name: studentName,
        transaction_type: transactionType,
        student_id: studentId || null,
        status: 'waiting',
      })
      .select()
      .single();

    if (error) throw error;
    return data as QueueItem;
  };

  const MAX_CUSTOMERS_PER_WINDOW = 5;

  const getWindowQueue = (windowId: number) => {
    return queue.filter(q => q.window_id === windowId && q.status === 'in_progress');
  };

  const callNext = async (windowId: number) => {
    // Check if window already has max customers
    const windowQueue = getWindowQueue(windowId);
    if (windowQueue.length >= MAX_CUSTOMERS_PER_WINDOW) {
      return null;
    }

    // Find next waiting item
    const waitingItems = queue.filter(q => q.status === 'waiting');
    if (waitingItems.length === 0) return null;

    const nextItem = waitingItems[0];

    // Update queue item
    await supabase
      .from('queue')
      .update({
        status: 'in_progress',
        window_id: windowId,
        called_at: new Date().toISOString(),
      })
      .eq('id', nextItem.id);

    // Update window's current_queue_id to the first item if not set
    if (windowQueue.length === 0) {
      await supabase
        .from('windows')
        .update({ current_queue_id: nextItem.id })
        .eq('id', windowId);
    }

    return nextItem;
  };

  const completeTransaction = async (queueId: string, windowId: number) => {
    await supabase
      .from('queue')
      .update({
        status: 'completed',
        completed_at: new Date().toISOString(),
      })
      .eq('id', queueId);

    // Find remaining items in this window's queue
    const remainingItems = queue.filter(
      q => q.window_id === windowId && q.status === 'in_progress' && q.id !== queueId
    );

    // Update window's current_queue_id to next item or null
    const nextCurrentId = remainingItems.length > 0 
      ? remainingItems.sort((a, b) => a.queue_number - b.queue_number)[0].id 
      : null;

    await supabase
      .from('windows')
      .update({ current_queue_id: nextCurrentId })
      .eq('id', windowId);
  };

  const submitFeedback = async (queueId: string, rating: number, comment?: string) => {
    const { error } = await supabase
      .from('feedback')
      .insert({
        queue_id: queueId,
        rating,
        comment: comment || null,
      });

    if (error) throw error;
  };

  // Derived state
  const waitingQueue = queue.filter(q => q.status === 'waiting');
  const inProgressQueue = queue.filter(q => q.status === 'in_progress');
  const completedQueue = queue.filter(q => q.status === 'completed');

  return {
    queue,
    windows,
    loading,
    waitingQueue,
    inProgressQueue,
    completedQueue,
    createQueueItem,
    callNext,
    completeTransaction,
    submitFeedback,
    getWindowQueue,
    MAX_CUSTOMERS_PER_WINDOW,
    refetch: () => Promise.all([fetchQueue(), fetchWindows()]),
  };
}
