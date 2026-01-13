import { useQueue } from '@/hooks/useQueue';
import { TRANSACTION_LABELS } from '@/types/queue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Users, Clock } from 'lucide-react';

const WINDOW_COLORS = [
  'bg-window-1',
  'bg-window-2', 
  'bg-window-3',
  'bg-window-4',
];

export function QueueBoard() {
  const { windows, queue, waitingQueue, loading } = useQueue();

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
      </div>
    );
  }

  const upcomingQueue = waitingQueue.slice(0, 5);

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="text-center space-y-2">
        <h1 className="text-4xl md:text-5xl font-serif font-bold text-primary">
          University Registrar
        </h1>
        <p className="text-muted-foreground text-lg">Queue Status Board</p>
      </div>

      {/* Now Serving - Windows */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {windows.map((window, index) => {
          const currentItem = queue.find(q => q.id === window.current_queue_id);
          
          return (
            <Card 
              key={window.id} 
              className={`queue-card overflow-hidden ${currentItem ? 'animate-pulse-glow' : ''}`}
            >
              <CardHeader className={`${WINDOW_COLORS[index]} text-primary-foreground py-4`}>
                <CardTitle className="text-center text-xl font-semibold">
                  Window {window.window_number}
                </CardTitle>
              </CardHeader>
              <CardContent className="p-6 text-center">
                {currentItem ? (
                  <div className="space-y-3 animate-slide-in-bottom">
                    <div className="queue-number text-primary animate-number-change">
                      {String(currentItem.queue_number).padStart(3, '0')}
                    </div>
                    <Badge variant="secondary" className="text-sm">
                      {TRANSACTION_LABELS[currentItem.transaction_type]}
                    </Badge>
                    <p className="text-muted-foreground text-sm truncate">
                      {currentItem.student_name}
                    </p>
                  </div>
                ) : (
                  <div className="py-8 text-muted-foreground">
                    <p className="text-lg font-medium">Available</p>
                    <p className="text-sm">Ready to serve</p>
                  </div>
                )}
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Upcoming Queue */}
      <Card className="queue-card">
        <CardHeader className="gradient-primary text-primary-foreground">
          <CardTitle className="flex items-center gap-3">
            <Users className="h-6 w-6" />
            <span className="text-2xl font-serif">Upcoming Queue</span>
            <Badge variant="secondary" className="ml-auto text-lg px-4">
              {waitingQueue.length} waiting
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent className="p-6">
          {upcomingQueue.length > 0 ? (
            <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
              {upcomingQueue.map((item, index) => (
                <div
                  key={item.id}
                  className={`
                    p-4 rounded-xl text-center transition-all duration-300
                    ${index === 0 
                      ? 'bg-accent/20 border-2 border-accent scale-105' 
                      : 'bg-muted hover:bg-muted/80'
                    }
                  `}
                >
                  <div className={`text-3xl font-bold ${index === 0 ? 'text-accent-foreground' : 'text-foreground'}`}>
                    {String(item.queue_number).padStart(3, '0')}
                  </div>
                  <p className="text-xs text-muted-foreground mt-1 truncate">
                    {TRANSACTION_LABELS[item.transaction_type]}
                  </p>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12 text-muted-foreground">
              <Clock className="h-12 w-12 mx-auto mb-4 opacity-50" />
              <p className="text-lg">No one in queue</p>
              <p className="text-sm">Create a transaction to get started</p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
