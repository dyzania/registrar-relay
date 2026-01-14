import { useState, useEffect, useMemo } from 'react';
import { useQueue } from '@/hooks/useQueue';
import { supabase } from '@/integrations/supabase/client';
import { TRANSACTION_LABELS, TransactionType } from '@/types/queue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import { Ticket, User, FileText, CheckCircle, Clock, Users, X } from 'lucide-react';
import { FeedbackModal } from './FeedbackModal';

export function CreateTransaction() {
  const [studentName, setStudentName] = useState('');
  const [studentId, setStudentId] = useState('');
  const [transactionType, setTransactionType] = useState<TransactionType | ''>('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [createdTicket, setCreatedTicket] = useState<{ id: string; number: number } | null>(null);
  const [showFeedback, setShowFeedback] = useState(false);
  const [currentStatus, setCurrentStatus] = useState<string>('waiting');

  const { createQueueItem, windows, waitingQueue } = useQueue();
  const { toast } = useToast();

  // Calculate queue position (how many people are ahead)
  const queuePosition = useMemo(() => {
    if (!createdTicket || currentStatus !== 'waiting') return 0;
    const position = waitingQueue.findIndex(q => q.id === createdTicket.id);
    return position === -1 ? 0 : position;
  }, [createdTicket, waitingQueue, currentStatus]);

  // Calculate available services (not disabled by ALL windows)
  const availableServices = useMemo(() => {
    const activeWindows = windows.filter(w => w.is_active);
    if (activeWindows.length === 0) return Object.keys(TRANSACTION_LABELS) as TransactionType[];

    return (Object.keys(TRANSACTION_LABELS) as TransactionType[]).filter(service => {
      // Service is available if at least one active window doesn't have it disabled
      return activeWindows.some(window => {
        const disabledServices = window.disabled_services || [];
        return !disabledServices.includes(service);
      });
    });
  }, [windows]);

  // Listen for status changes on the created ticket
  useEffect(() => {
    if (!createdTicket) return;

    const channel = supabase
      .channel(`ticket-${createdTicket.id}`)
      .on(
        'postgres_changes',
        {
          event: 'UPDATE',
          schema: 'public',
          table: 'queue',
          filter: `id=eq.${createdTicket.id}`,
        },
        (payload) => {
          const newStatus = payload.new.status as string;
          setCurrentStatus(newStatus);
          
          if (newStatus === 'completed') {
            setShowFeedback(true);
          } else if (newStatus === 'in_progress') {
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
  }, [createdTicket, toast]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!studentName || !transactionType) {
      toast({
        title: 'Missing Information',
        description: 'Please fill in all required fields.',
        variant: 'destructive',
      });
      return;
    }

    setIsSubmitting(true);
    try {
      const item = await createQueueItem(studentName, transactionType, studentId);
      setCreatedTicket({ id: item.id, number: item.queue_number });
      setCurrentStatus('waiting');
      toast({
        title: 'Ticket Created!',
        description: `Your queue number is ${String(item.queue_number).padStart(3, '0')}`,
      });
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to create ticket. Please try again.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const resetForm = () => {
    setStudentName('');
    setStudentId('');
    setTransactionType('');
    setCreatedTicket(null);
    setShowFeedback(false);
    setCurrentStatus('waiting');
  };

  const handleCancelTicket = async () => {
    if (!createdTicket) return;
    
    try {
      const { error } = await supabase
        .from('queue')
        .update({ status: 'cancelled' })
        .eq('id', createdTicket.id);
      
      if (error) throw error;
      
      toast({
        title: 'Ticket Cancelled',
        description: 'Your queue ticket has been cancelled.',
      });
      resetForm();
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to cancel ticket. Please try again.',
        variant: 'destructive',
      });
    }
  };

  if (createdTicket !== null) {
    return (
      <>
        <Card className="queue-card max-w-md mx-auto">
          <CardContent className="p-8 text-center space-y-6">
            <div className={`w-20 h-20 rounded-full flex items-center justify-center mx-auto ${
              currentStatus === 'in_progress' 
                ? 'bg-accent/10 animate-pulse' 
                : currentStatus === 'completed'
                ? 'bg-success/10'
                : 'bg-primary/10'
            }`}>
              {currentStatus === 'in_progress' ? (
                <Clock className="w-10 h-10 text-accent" />
              ) : (
                <CheckCircle className="w-10 h-10 text-success" />
              )}
            </div>
            <div>
              <h2 className="text-2xl font-serif font-bold text-foreground mb-2">
                {currentStatus === 'in_progress' ? 'Your Turn!' : 
                 currentStatus === 'completed' ? 'Transaction Complete!' : 
                 'Ticket Created!'}
              </h2>
              <p className="text-muted-foreground">
                {currentStatus === 'in_progress' 
                  ? 'Please proceed to the window' 
                  : currentStatus === 'completed'
                  ? 'Thank you for your patience'
                  : 'Your queue number is'}
              </p>
            </div>
            <div className={`queue-number py-4 px-8 rounded-2xl inline-block ${
              currentStatus === 'in_progress' 
                ? 'text-accent bg-accent/5' 
                : 'text-primary bg-primary/5'
            }`}>
              {String(createdTicket.number).padStart(3, '0')}
            </div>
            {currentStatus === 'waiting' && (
              <div className="space-y-4">
                {/* Queue Position Indicator */}
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
                
                <p className="text-sm text-muted-foreground">
                  Please wait for your number to be called. Watch the queue board for updates.
                </p>
                
                {/* Cancel Button */}
                <Button 
                  onClick={handleCancelTicket} 
                  variant="outline" 
                  className="w-full text-destructive border-destructive/30 hover:bg-destructive/10"
                >
                  <X className="w-4 h-4 mr-2" />
                  Cancel Ticket
                </Button>
              </div>
            )}
            {currentStatus === 'completed' && (
              <Button onClick={resetForm} variant="outline" className="w-full">
                Create Another Ticket
              </Button>
            )}
          </CardContent>
        </Card>
        
        <FeedbackModal
          open={showFeedback}
          onClose={() => {
            setShowFeedback(false);
            resetForm();
          }}
          queueId={createdTicket.id}
          queueNumber={createdTicket.number}
        />
      </>
    );
  }

  return (
    <Card className="queue-card max-w-md mx-auto">
      <CardHeader className="space-y-1">
        <div className="w-14 h-14 rounded-xl gradient-primary flex items-center justify-center mb-2">
          <Ticket className="w-7 h-7 text-primary-foreground" />
        </div>
        <CardTitle className="text-2xl font-serif">Get Queue Number</CardTitle>
        <CardDescription>
          Fill in your details to receive a queue ticket
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-5">
          <div className="space-y-2">
            <Label htmlFor="name" className="flex items-center gap-2">
              <User className="w-4 h-4" />
              Full Name *
            </Label>
            <Input
              id="name"
              value={studentName}
              onChange={(e) => setStudentName(e.target.value)}
              placeholder="Enter your full name"
              className="h-12"
              required
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="studentId" className="flex items-center gap-2">
              <FileText className="w-4 h-4" />
              Student ID (Optional)
            </Label>
            <Input
              id="studentId"
              value={studentId}
              onChange={(e) => setStudentId(e.target.value)}
              placeholder="e.g., 2024-00001"
              className="h-12"
            />
          </div>

          <div className="space-y-2">
            <Label className="flex items-center gap-2">
              <Ticket className="w-4 h-4" />
              Transaction Type *
            </Label>
            <Select value={transactionType} onValueChange={(v) => setTransactionType(v as TransactionType)}>
              <SelectTrigger className="h-12">
                <SelectValue placeholder="Select transaction type" />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(TRANSACTION_LABELS).map(([value, label]) => {
                  const isAvailable = availableServices.includes(value as TransactionType);
                  return (
                    <SelectItem 
                      key={value} 
                      value={value}
                      disabled={!isAvailable}
                      className={!isAvailable ? 'opacity-50' : ''}
                    >
                      {label}{!isAvailable ? ' (Unavailable)' : ''}
                    </SelectItem>
                  );
                })}
              </SelectContent>
            </Select>
          </div>

          <Button
            type="submit"
            className="w-full h-12 text-lg font-semibold gradient-primary hover:opacity-90 transition-opacity"
            disabled={isSubmitting}
          >
            {isSubmitting ? 'Creating...' : 'Get Queue Number'}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}