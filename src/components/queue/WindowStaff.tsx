import { useState } from 'react';
import { useQueue } from '@/hooks/useQueue';
import { TRANSACTION_LABELS, QueueItem, TransactionType } from '@/types/queue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { useToast } from '@/hooks/use-toast';
import { UserCheck, CheckCircle, PhoneCall, Clock, Users, ListOrdered, Settings, ChevronDown, ChevronUp } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

interface WindowStaffProps {
  windowNumber: number;
}

const ALL_TRANSACTION_TYPES: TransactionType[] = [
  'grade_request',
  'enrollment',
  'document_request',
  'payment',
  'clearance',
  'other',
];

export function WindowStaff({ windowNumber }: WindowStaffProps) {
  const { windows, waitingQueue, callNext, completeTransaction, getWindowQueue, toggleService, MAX_CUSTOMERS_PER_WINDOW } = useQueue();
  const [isProcessing, setIsProcessing] = useState(false);
  const [servicesOpen, setServicesOpen] = useState(false);
  const { toast } = useToast();

  const window = windows.find(w => w.window_number === windowNumber);
  const windowQueue = window ? getWindowQueue(window.id) : [];
  const sortedWindowQueue = [...windowQueue].sort((a, b) => a.queue_number - b.queue_number);
  const disabledServices = window?.disabled_services || [];
  const enabledServicesCount = ALL_TRANSACTION_TYPES.length - disabledServices.length;
  
  // Filter waiting queue by enabled services for this window
  const eligibleWaitingQueue = waitingQueue.filter(
    q => !disabledServices.includes(q.transaction_type)
  );
  const canCallMore = windowQueue.length < MAX_CUSTOMERS_PER_WINDOW && eligibleWaitingQueue.length > 0;

  const handleCallNext = async () => {
    if (!window) return;
    setIsProcessing(true);
    try {
      const next = await callNext(window.id);
      if (next) {
        toast({
          title: 'Customer Called',
          description: `Queue #${String(next.queue_number).padStart(3, '0')} - ${next.student_name}`,
        });
      } else if (windowQueue.length >= MAX_CUSTOMERS_PER_WINDOW) {
        toast({
          title: 'Window Full',
          description: `Maximum ${MAX_CUSTOMERS_PER_WINDOW} customers per window.`,
        });
      } else {
        toast({
          title: 'No Eligible Customers',
          description: 'No customers waiting for enabled services.',
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

  const handleComplete = async (item: QueueItem) => {
    if (!window) return;
    setIsProcessing(true);
    try {
      await completeTransaction(item.id, window.id);
      toast({
        title: 'Transaction Completed',
        description: `Queue #${String(item.queue_number).padStart(3, '0')} marked as complete.`,
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

  const handleToggleService = async (service: TransactionType) => {
    if (!window) return;
    await toggleService(window.id, service);
    const isDisabling = !disabledServices.includes(service);
    toast({
      title: isDisabling ? 'Service Disabled' : 'Service Enabled',
      description: `${TRANSACTION_LABELS[service]} is now ${isDisabling ? 'disabled' : 'enabled'} at this window.`,
    });
  };

  return (
    <Card className="queue-card h-full">
      <CardHeader className="gradient-primary text-primary-foreground">
        <CardTitle className="flex items-center justify-between">
          <span className="flex items-center gap-2">
            <UserCheck className="w-5 h-5" />
            Window {windowNumber}
          </span>
          <div className="flex items-center gap-2">
            <Badge variant="secondary" className="flex items-center gap-1">
              <ListOrdered className="w-3 h-3" />
              {windowQueue.length}/{MAX_CUSTOMERS_PER_WINDOW}
            </Badge>
            <Badge variant="secondary" className="flex items-center gap-1">
              <Users className="w-3 h-3" />
              {eligibleWaitingQueue.length} eligible
            </Badge>
          </div>
        </CardTitle>
      </CardHeader>
      <CardContent className="p-6 space-y-4">
        {/* Service Settings */}
        <Collapsible open={servicesOpen} onOpenChange={setServicesOpen}>
          <CollapsibleTrigger asChild>
            <Button variant="outline" className="w-full justify-between">
              <span className="flex items-center gap-2">
                <Settings className="w-4 h-4" />
                Services ({enabledServicesCount}/{ALL_TRANSACTION_TYPES.length} enabled)
              </span>
              {servicesOpen ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
            </Button>
          </CollapsibleTrigger>
          <CollapsibleContent className="mt-3">
            <div className="space-y-2 p-4 bg-muted/50 rounded-lg">
              {ALL_TRANSACTION_TYPES.map((service) => {
                const isEnabled = !disabledServices.includes(service);
                return (
                  <div key={service} className="flex items-center justify-between py-2">
                    <span className={`text-sm ${!isEnabled ? 'text-muted-foreground line-through' : ''}`}>
                      {TRANSACTION_LABELS[service]}
                    </span>
                    <Switch
                      checked={isEnabled}
                      onCheckedChange={() => handleToggleService(service)}
                    />
                  </div>
                );
              })}
            </div>
          </CollapsibleContent>
        </Collapsible>

        {/* Current Customers in Window */}
        {sortedWindowQueue.length > 0 ? (
          <div className="space-y-3">
            <p className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Users className="w-4 h-4" />
              Serving {sortedWindowQueue.length} Customer{sortedWindowQueue.length > 1 ? 's' : ''}
            </p>
            {sortedWindowQueue.map((item, index) => (
              <div
                key={item.id}
                className={`border rounded-xl p-4 space-y-3 ${
                  index === 0 ? 'bg-success/10 border-success/20' : 'bg-accent/5 border-border'
                }`}
              >
                <div className="flex items-start justify-between">
                  <div>
                    <div className="flex items-center gap-2">
                      {index === 0 && (
                        <Badge className="bg-success text-success-foreground text-xs">Now Serving</Badge>
                      )}
                      <span className="queue-number text-2xl">
                        {String(item.queue_number).padStart(3, '0')}
                      </span>
                    </div>
                    <p className="font-medium text-foreground mt-1">{item.student_name}</p>
                    <Badge variant="outline" className="mt-1">
                      {TRANSACTION_LABELS[item.transaction_type]}
                    </Badge>
                    {item.student_id && (
                      <p className="text-xs text-muted-foreground mt-1">ID: {item.student_id}</p>
                    )}
                  </div>
                  <Button
                    size="sm"
                    onClick={() => handleComplete(item)}
                    disabled={isProcessing}
                    className="bg-success hover:bg-success/90"
                  >
                    <CheckCircle className="w-4 h-4 mr-1" />
                    Done
                  </Button>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-6">
            <div className="w-14 h-14 rounded-full bg-muted flex items-center justify-center mx-auto mb-3">
              <Clock className="w-7 h-7 text-muted-foreground" />
            </div>
            <p className="text-lg font-medium text-muted-foreground">No Active Customers</p>
            <p className="text-sm text-muted-foreground">Call customers to begin serving</p>
          </div>
        )}

        {/* Call Next Button */}
        <Button
          onClick={handleCallNext}
          disabled={isProcessing || !canCallMore}
          className="w-full h-12 text-lg font-semibold"
        >
          <PhoneCall className="w-5 h-5 mr-2" />
          Call Next ({eligibleWaitingQueue.length} eligible)
        </Button>

        {windowQueue.length >= MAX_CUSTOMERS_PER_WINDOW && (
          <p className="text-center text-sm text-muted-foreground">
            Window at maximum capacity
          </p>
        )}

        {disabledServices.length > 0 && (
          <p className="text-center text-xs text-muted-foreground">
            Disabled: {disabledServices.map(s => TRANSACTION_LABELS[s as TransactionType]).join(', ')}
          </p>
        )}

        {/* Upcoming Preview */}
        {eligibleWaitingQueue.length > 0 && windowQueue.length < MAX_CUSTOMERS_PER_WINDOW && (
          <div className="pt-4 border-t">
            <p className="text-sm font-medium text-muted-foreground mb-3">Next Eligible</p>
            <div className="space-y-2">
              {eligibleWaitingQueue.slice(0, 3).map((item, index) => (
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
