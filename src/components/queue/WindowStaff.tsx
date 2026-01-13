import { useState } from 'react';
import { useQueue } from '@/hooks/useQueue';
import { TRANSACTION_LABELS } from '@/types/queue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/use-toast';
import { UserCheck, CheckCircle, PhoneCall, Clock, Users } from 'lucide-react';

interface WindowStaffProps {
  windowNumber: number;
}

export function WindowStaff({ windowNumber }: WindowStaffProps) {
  const { windows, queue, waitingQueue, callNext, completeTransaction } = useQueue();
  const [isProcessing, setIsProcessing] = useState(false);
  const { toast } = useToast();

  const window = windows.find(w => w.window_number === windowNumber);
  const currentItem = queue.find(q => q.id === window?.current_queue_id);

  const handleCallNext = async () => {
    if (!window) return;
    setIsProcessing(true);
    try {
      const next = await callNext(window.id);
      if (next) {
        toast({
          title: 'Next Customer Called',
          description: `Queue #${String(next.queue_number).padStart(3, '0')} - ${next.student_name}`,
        });
      } else {
        toast({
          title: 'No Customers Waiting',
          description: 'The queue is empty.',
        });
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to call next customer.',
        variant: 'destructive',
      });
    } finally {
      setIsProcessing(false);
    }
  };

  const handleComplete = async () => {
    if (!window || !currentItem) return;
    setIsProcessing(true);
    try {
      await completeTransaction(currentItem.id, window.id);
      toast({
        title: 'Transaction Completed',
        description: `Queue #${String(currentItem.queue_number).padStart(3, '0')} marked as complete.`,
      });
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to complete transaction.',
        variant: 'destructive',
      });
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <Card className="queue-card h-full">
      <CardHeader className="gradient-primary text-primary-foreground">
        <CardTitle className="flex items-center justify-between">
          <span className="flex items-center gap-2">
            <UserCheck className="w-5 h-5" />
            Window {windowNumber}
          </span>
          <Badge variant="secondary" className="flex items-center gap-1">
            <Users className="w-3 h-3" />
            {waitingQueue.length} waiting
          </Badge>
        </CardTitle>
      </CardHeader>
      <CardContent className="p-6 space-y-6">
        {/* Current Customer */}
        {currentItem ? (
          <div className="space-y-4">
            <div className="bg-success/10 border border-success/20 rounded-xl p-6 text-center">
              <p className="text-sm text-muted-foreground mb-1">Now Serving</p>
              <div className="queue-number text-success mb-2">
                {String(currentItem.queue_number).padStart(3, '0')}
              </div>
              <p className="font-medium text-foreground">{currentItem.student_name}</p>
              <Badge variant="outline" className="mt-2">
                {TRANSACTION_LABELS[currentItem.transaction_type]}
              </Badge>
              {currentItem.student_id && (
                <p className="text-sm text-muted-foreground mt-2">
                  ID: {currentItem.student_id}
                </p>
              )}
            </div>

            <Button
              onClick={handleComplete}
              disabled={isProcessing}
              className="w-full h-12 text-lg font-semibold bg-success hover:bg-success/90"
            >
              <CheckCircle className="w-5 h-5 mr-2" />
              Complete Transaction
            </Button>
          </div>
        ) : (
          <div className="text-center py-8">
            <div className="w-16 h-16 rounded-full bg-muted flex items-center justify-center mx-auto mb-4">
              <Clock className="w-8 h-8 text-muted-foreground" />
            </div>
            <p className="text-lg font-medium text-muted-foreground">No Active Customer</p>
            <p className="text-sm text-muted-foreground">Call the next customer to begin</p>
          </div>
        )}

        {/* Call Next Button */}
        {!currentItem && (
          <Button
            onClick={handleCallNext}
            disabled={isProcessing || waitingQueue.length === 0}
            className="w-full h-12 text-lg font-semibold"
          >
            <PhoneCall className="w-5 h-5 mr-2" />
            Call Next ({waitingQueue.length})
          </Button>
        )}

        {/* Upcoming Preview */}
        {waitingQueue.length > 0 && (
          <div className="pt-4 border-t">
            <p className="text-sm font-medium text-muted-foreground mb-3">Next in Line</p>
            <div className="space-y-2">
              {waitingQueue.slice(0, 3).map((item, index) => (
                <div
                  key={item.id}
                  className={`flex items-center justify-between p-3 rounded-lg ${
                    index === 0 ? 'bg-accent/10' : 'bg-muted/50'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <span className="font-bold text-lg">
                      {String(item.queue_number).padStart(3, '0')}
                    </span>
                    <span className="text-sm text-muted-foreground truncate max-w-[120px]">
                      {item.student_name}
                    </span>
                  </div>
                  <Badge variant="secondary" className="text-xs">
                    {TRANSACTION_LABELS[item.transaction_type].split(' ')[0]}
                  </Badge>
                </div>
              ))}
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
