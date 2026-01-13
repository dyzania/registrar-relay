import { useQueue } from '@/hooks/useQueue';
import { TRANSACTION_LABELS } from '@/types/queue';
import { Badge } from '@/components/ui/badge';

const WINDOW_COLORS = [
  'from-window-1 to-blue-700',
  'from-window-2 to-emerald-700',
  'from-window-3 to-purple-700',
  'from-window-4 to-orange-700',
];

const Display = () => {
  const { windows, queue, waitingQueue, loading } = useQueue();

  if (loading) {
    return (
      <div className="min-h-screen bg-primary flex items-center justify-center">
        <div className="animate-spin rounded-full h-16 w-16 border-4 border-primary-foreground border-t-transparent"></div>
      </div>
    );
  }

  const upcomingQueue = waitingQueue.slice(0, 5);

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary via-primary to-blue-900 text-primary-foreground p-8">
      {/* Header */}
      <div className="text-center mb-8">
        <h1 className="text-5xl md:text-6xl font-serif font-bold mb-2">
          University Registrar
        </h1>
        <p className="text-xl opacity-80">Queue Display Board</p>
      </div>

      {/* Now Serving Grid */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        {windows.map((window, index) => {
          const currentItem = queue.find(q => q.id === window.current_queue_id);
          
          return (
            <div
              key={window.id}
              className={`
                bg-gradient-to-br ${WINDOW_COLORS[index]}
                rounded-3xl p-6 shadow-2xl
                ${currentItem ? 'animate-pulse-soft' : 'opacity-80'}
              `}
            >
              <div className="text-center">
                <p className="text-lg font-medium opacity-80 mb-2">
                  Window {window.window_number}
                </p>
                {currentItem ? (
                  <div className="animate-number-change">
                    <div className="text-7xl md:text-8xl font-bold tracking-tight">
                      {String(currentItem.queue_number).padStart(3, '0')}
                    </div>
                    <Badge className="mt-4 bg-white/20 text-white border-0 text-sm px-4 py-1">
                      {TRANSACTION_LABELS[currentItem.transaction_type]}
                    </Badge>
                  </div>
                ) : (
                  <div className="py-6">
                    <p className="text-3xl font-light opacity-60">—</p>
                    <p className="text-sm opacity-50 mt-2">Available</p>
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {/* Upcoming Queue */}
      <div className="bg-white/10 backdrop-blur-lg rounded-3xl p-8">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-3xl font-serif font-bold">Next in Queue</h2>
          <Badge variant="secondary" className="text-lg px-6 py-2 bg-accent text-accent-foreground">
            {waitingQueue.length} waiting
          </Badge>
        </div>
        
        {upcomingQueue.length > 0 ? (
          <div className="grid grid-cols-5 gap-4">
            {upcomingQueue.map((item, index) => (
              <div
                key={item.id}
                className={`
                  p-6 rounded-2xl text-center transition-all
                  ${index === 0 
                    ? 'bg-accent text-accent-foreground scale-110 shadow-lg' 
                    : 'bg-white/10'
                  }
                `}
              >
                <div className={`text-4xl md:text-5xl font-bold ${index === 0 ? '' : 'opacity-90'}`}>
                  {String(item.queue_number).padStart(3, '0')}
                </div>
                <p className={`text-sm mt-2 ${index === 0 ? 'opacity-80' : 'opacity-60'}`}>
                  {TRANSACTION_LABELS[item.transaction_type].split(' ')[0]}
                </p>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-12 opacity-60">
            <p className="text-2xl">Queue is empty</p>
          </div>
        )}
      </div>

      {/* Footer */}
      <div className="text-center mt-8 opacity-50 text-sm">
        <p>Please wait for your number to be called</p>
      </div>
    </div>
  );
};

export default Display;
