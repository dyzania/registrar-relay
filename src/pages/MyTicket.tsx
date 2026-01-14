import { useState, useEffect, useMemo } from 'react';
import { useQueue } from '@/hooks/useQueue';
import { supabase } from '@/integrations/supabase/client';
import { TRANSACTION_LABELS } from '@/types/queue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/hooks/use-toast';
import { Search, Clock, CheckCircle, Users, X, AlertCircle, Ticket } from 'lucide-react';
import { FeedbackModal } from '@/components/queue/FeedbackModal';

const MyTicket = () => {
  const [searchNumber, setSearchNumber] = useState('');
  const [isSearching, setIsSearching] = useState(false);
  const [ticket, setTicket] = useState<{
    id: string;
    queue_number: number;
    student_name: string;
    transaction_type: string;
    status: string;
    window_id: number | null;
  } | null>(null);
  const [notFound, setNotFound] = useState(false);
  const [showFeedback, setShowFeedback] = useState(false);

  const { waitingQueue, windows } = useQueue();
  const { toast } = useToast();

  // Calculate queue position
  const queuePosition = useMemo(() => {
    if (!ticket || ticket.status !== 'waiting') return 0;
    const position = waitingQueue.findIndex(q => q.id === ticket.id);
    return position === -1 ? 0 : position;
  }, [ticket, waitingQueue]);

  // Get window number if in progress
  const assignedWindow = useMemo(() => {
    if (!ticket || !ticket.window_id) return null;
    return windows.find(w => w.id === ticket.window_id);
  }, [ticket, windows]);

  // Listen for real-time updates on the ticket
  useEffect(() => {
    if (!ticket) return;

    const channel = supabase
      .channel(`my-ticket-${ticket.id}`)
      .on(
        'postgres_changes',
        {
          event: 'UPDATE',
          schema: 'public',
          table: 'queue',
          filter: `id=eq.${ticket.id}`,
        },
        (payload) => {
          const newData = payload.new as typeof ticket;
          setTicket(prev => prev ? { ...prev, ...newData } : null);
          
          if (newData.status === 'completed') {
            setShowFeedback(true);
          } else if (newData.status === 'in_progress') {
            toast({
              title: 'Your turn!',
              description: 'Please proceed to the window.',
            });
          }
        }
      )
      .subscribe();

    return () => {
      supabase.removeChannel(channel);
    };
  }, [ticket?.id, toast]);

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!searchNumber.trim()) return;

    setIsSearching(true);
    setNotFound(false);

    try {
      const queueNum = parseInt(searchNumber, 10);
      
      const { data, error } = await supabase
        .from('queue')
        .select('*')
        .eq('queue_number', queueNum)
        .order('created_at', { ascending: false })
        .limit(1)
        .single();

      if (error || !data) {
        setNotFound(true);
        setTicket(null);
      } else {
        setTicket(data);
        setNotFound(false);
      }
    } catch {
      setNotFound(true);
      setTicket(null);
    } finally {
      setIsSearching(false);
    }
  };

  const handleCancelTicket = async () => {
    if (!ticket) return;

    try {
      const { error } = await supabase
        .from('queue')
        .update({ status: 'cancelled' })
        .eq('id', ticket.id);

      if (error) throw error;

      toast({
        title: 'Ticket Cancelled',
        description: 'Your queue ticket has been cancelled.',
      });
      setTicket(null);
      setSearchNumber('');
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to cancel ticket. Please try again.',
        variant: 'destructive',
      });
    }
  };

  const resetSearch = () => {
    setTicket(null);
    setSearchNumber('');
    setNotFound(false);
    setShowFeedback(false);
  };

  const getStatusConfig = (status: string) => {
    switch (status) {
      case 'waiting':
        return {
          icon: Clock,
          color: 'text-primary',
          bg: 'bg-primary/10',
          label: 'Waiting',
          description: 'Please wait for your number to be called',
        };
      case 'in_progress':
        return {
          icon: Clock,
          color: 'text-accent',
          bg: 'bg-accent/10 animate-pulse',
          label: 'Your Turn!',
          description: 'Please proceed to the window',
        };
      case 'completed':
        return {
          icon: CheckCircle,
          color: 'text-success',
          bg: 'bg-success/10',
          label: 'Completed',
          description: 'Transaction has been completed',
        };
      case 'cancelled':
        return {
          icon: X,
          color: 'text-destructive',
          bg: 'bg-destructive/10',
          label: 'Cancelled',
          description: 'This ticket has been cancelled',
        };
      default:
        return {
          icon: AlertCircle,
          color: 'text-muted-foreground',
          bg: 'bg-muted',
          label: 'Unknown',
          description: '',
        };
    }
  };

  // Show ticket details
  if (ticket) {
    const statusConfig = getStatusConfig(ticket.status);
    const StatusIcon = statusConfig.icon;

    return (
      <div className="container mx-auto px-4 py-8 min-h-[calc(100vh-4rem)] flex items-center justify-center">
        <Card className="queue-card max-w-md w-full">
          <CardContent className="p-8 text-center space-y-6">
            {/* Status Icon */}
            <div className={`w-20 h-20 rounded-full flex items-center justify-center mx-auto ${statusConfig.bg}`}>
              <StatusIcon className={`w-10 h-10 ${statusConfig.color}`} />
            </div>

            {/* Status Label */}
            <div>
              <h2 className="text-2xl font-serif font-bold text-foreground mb-2">
                {statusConfig.label}
              </h2>
              <p className="text-muted-foreground">{statusConfig.description}</p>
            </div>

            {/* Queue Number */}
            <div className={`queue-number py-4 px-8 rounded-2xl inline-block ${
              ticket.status === 'in_progress' 
                ? 'text-accent bg-accent/5' 
                : 'text-primary bg-primary/5'
            }`}>
              {String(ticket.queue_number).padStart(3, '0')}
            </div>

            {/* Ticket Details */}
            <div className="bg-muted/30 rounded-xl p-4 space-y-2 text-left">
              <div className="flex justify-between">
                <span className="text-muted-foreground text-sm">Name</span>
                <span className="font-medium text-sm">{ticket.student_name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground text-sm">Transaction</span>
                <span className="font-medium text-sm">
                  {TRANSACTION_LABELS[ticket.transaction_type as keyof typeof TRANSACTION_LABELS]}
                </span>
              </div>
              {assignedWindow && ticket.status === 'in_progress' && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground text-sm">Window</span>
                  <span className="font-medium text-sm text-accent">
                    Window {assignedWindow.window_number}
                  </span>
                </div>
              )}
            </div>

            {/* Queue Position for waiting tickets */}
            {ticket.status === 'waiting' && (
              <div className="bg-muted/50 rounded-xl p-4 space-y-2">
                <div className="flex items-center justify-center gap-2 text-muted-foreground">
                  <Users className="w-4 h-4" />
                  <span className="text-sm font-medium">Queue Position</span>
                </div>
                <div className="text-center">
                  {queuePosition === 0 ? (
                    <p className="text-lg font-semibold text-accent">You're next!</p>
                  ) : (
                    <p className="text-lg">
                      <span className="font-bold text-2xl text-primary">{queuePosition}</span>
                      <span className="text-muted-foreground"> {queuePosition === 1 ? 'person' : 'people'} ahead of you</span>
                    </p>
                  )}
                </div>
              </div>
            )}

            {/* Actions */}
            <div className="space-y-3">
              {ticket.status === 'waiting' && (
                <Button
                  onClick={handleCancelTicket}
                  variant="outline"
                  className="w-full text-destructive border-destructive/30 hover:bg-destructive/10"
                >
                  <X className="w-4 h-4 mr-2" />
                  Cancel Ticket
                </Button>
              )}
              <Button onClick={resetSearch} variant="ghost" className="w-full">
                Check Another Ticket
              </Button>
            </div>
          </CardContent>
        </Card>

        <FeedbackModal
          open={showFeedback}
          onClose={() => {
            setShowFeedback(false);
            resetSearch();
          }}
          queueId={ticket.id}
          queueNumber={ticket.queue_number}
        />
      </div>
    );
  }

  // Search form
  return (
    <div className="container mx-auto px-4 py-8 min-h-[calc(100vh-4rem)] flex items-center justify-center">
      <Card className="queue-card max-w-md w-full">
        <CardHeader className="space-y-1">
          <div className="w-14 h-14 rounded-xl gradient-primary flex items-center justify-center mb-2">
            <Ticket className="w-7 h-7 text-primary-foreground" />
          </div>
          <CardTitle className="text-2xl font-serif">Check My Ticket</CardTitle>
          <CardDescription>
            Enter your queue number to view your ticket status
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSearch} className="space-y-5">
            <div className="space-y-2">
              <Label htmlFor="queueNumber" className="flex items-center gap-2">
                <Search className="w-4 h-4" />
                Queue Number
              </Label>
              <Input
                id="queueNumber"
                type="number"
                value={searchNumber}
                onChange={(e) => setSearchNumber(e.target.value)}
                placeholder="e.g., 001"
                className="h-12 text-center text-2xl font-bold tracking-wider"
                min="1"
                required
              />
            </div>

            {notFound && (
              <div className="bg-destructive/10 text-destructive rounded-lg p-3 flex items-center gap-2">
                <AlertCircle className="w-4 h-4 flex-shrink-0" />
                <span className="text-sm">Ticket not found. Please check the number and try again.</span>
              </div>
            )}

            <Button
              type="submit"
              className="w-full h-12 text-lg font-semibold gradient-primary hover:opacity-90 transition-opacity"
              disabled={isSearching}
            >
              {isSearching ? 'Searching...' : 'Find My Ticket'}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
};

export default MyTicket;
