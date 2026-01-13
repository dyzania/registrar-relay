import { useState } from 'react';
import { useQueue } from '@/hooks/useQueue';
import { TRANSACTION_LABELS, TransactionType } from '@/types/queue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import { Ticket, User, FileText, CheckCircle } from 'lucide-react';

export function CreateTransaction() {
  const [studentName, setStudentName] = useState('');
  const [studentId, setStudentId] = useState('');
  const [transactionType, setTransactionType] = useState<TransactionType | ''>('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [createdTicket, setCreatedTicket] = useState<number | null>(null);

  const { createQueueItem } = useQueue();
  const { toast } = useToast();

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
      setCreatedTicket(item.queue_number);
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
  };

  if (createdTicket !== null) {
    return (
      <Card className="queue-card max-w-md mx-auto">
        <CardContent className="p-8 text-center space-y-6">
          <div className="w-20 h-20 rounded-full bg-success/10 flex items-center justify-center mx-auto">
            <CheckCircle className="w-10 h-10 text-success" />
          </div>
          <div>
            <h2 className="text-2xl font-serif font-bold text-foreground mb-2">
              Ticket Created!
            </h2>
            <p className="text-muted-foreground">Your queue number is</p>
          </div>
          <div className="queue-number text-primary py-4 px-8 bg-primary/5 rounded-2xl inline-block">
            {String(createdTicket).padStart(3, '0')}
          </div>
          <p className="text-sm text-muted-foreground">
            Please wait for your number to be called. Watch the queue board for updates.
          </p>
          <Button onClick={resetForm} variant="outline" className="w-full">
            Create Another Ticket
          </Button>
        </CardContent>
      </Card>
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
                {Object.entries(TRANSACTION_LABELS).map(([value, label]) => (
                  <SelectItem key={value} value={value}>
                    {label}
                  </SelectItem>
                ))}
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
