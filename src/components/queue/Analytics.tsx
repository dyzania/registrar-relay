import { useEffect, useState } from 'react';
import { supabase } from '@/integrations/supabase/client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TRANSACTION_LABELS, TransactionType, Feedback } from '@/types/queue';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { TrendingUp, Users, Clock, Star, CheckCircle } from 'lucide-react';

interface AnalyticsData {
  totalToday: number;
  completedToday: number;
  averageWaitTime: number;
  averageRating: number;
  transactionBreakdown: { name: string; value: number }[];
  hourlyData: { hour: string; count: number }[];
}

const COLORS = ['#1e3a5f', '#2d8b57', '#e6a817', '#7c3aed', '#f97316', '#64748b'];

export function Analytics() {
  const [data, setData] = useState<AnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchAnalytics = async () => {
      const today = new Date().toISOString().split('T')[0];
      
      // Fetch today's queue items
      const { data: queueData } = await supabase
        .from('queue')
        .select('*')
        .gte('created_at', today);

      // Fetch feedback
      const { data: feedbackData } = await supabase
        .from('feedback')
        .select('rating')
        .gte('created_at', today);

      if (queueData) {
        const completed = queueData.filter(q => q.status === 'completed');
        
        // Calculate average wait time
        let totalWaitTime = 0;
        let waitCount = 0;
        completed.forEach(q => {
          if (q.created_at && q.called_at) {
            const wait = new Date(q.called_at).getTime() - new Date(q.created_at).getTime();
            totalWaitTime += wait;
            waitCount++;
          }
        });

        // Transaction breakdown
        const breakdown: Record<string, number> = {};
        queueData.forEach(q => {
          const label = TRANSACTION_LABELS[q.transaction_type as TransactionType];
          breakdown[label] = (breakdown[label] || 0) + 1;
        });

        // Hourly distribution
        const hourly: Record<string, number> = {};
        queueData.forEach(q => {
          const hour = new Date(q.created_at).getHours();
          const label = `${hour}:00`;
          hourly[label] = (hourly[label] || 0) + 1;
        });

        // Average rating
        const ratings = (feedbackData as Feedback[] || []).map(f => f.rating);
        const avgRating = ratings.length > 0 
          ? ratings.reduce((a, b) => a + b, 0) / ratings.length 
          : 0;

        setData({
          totalToday: queueData.length,
          completedToday: completed.length,
          averageWaitTime: waitCount > 0 ? Math.round(totalWaitTime / waitCount / 60000) : 0,
          averageRating: Math.round(avgRating * 10) / 10,
          transactionBreakdown: Object.entries(breakdown).map(([name, value]) => ({ name, value })),
          hourlyData: Object.entries(hourly)
            .map(([hour, count]) => ({ hour, count }))
            .sort((a, b) => parseInt(a.hour) - parseInt(b.hour)),
        });
      }
      
      setLoading(false);
    };

    fetchAnalytics();
    const interval = setInterval(fetchAnalytics, 30000); // Refresh every 30s
    
    return () => clearInterval(interval);
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
      </div>
    );
  }

  if (!data) return null;

  return (
    <div className="space-y-6">
      <div className="text-center space-y-2">
        <h1 className="text-4xl font-serif font-bold text-primary">Analytics Dashboard</h1>
        <p className="text-muted-foreground">Today's Queue Performance</p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="queue-card">
          <CardContent className="p-6">
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-primary/10">
                <Users className="w-6 h-6 text-primary" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Total Today</p>
                <p className="text-3xl font-bold text-foreground">{data.totalToday}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="queue-card">
          <CardContent className="p-6">
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-success/10">
                <CheckCircle className="w-6 h-6 text-success" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Completed</p>
                <p className="text-3xl font-bold text-foreground">{data.completedToday}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="queue-card">
          <CardContent className="p-6">
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-warning/10">
                <Clock className="w-6 h-6 text-warning" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Avg Wait</p>
                <p className="text-3xl font-bold text-foreground">{data.averageWaitTime}m</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="queue-card">
          <CardContent className="p-6">
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-accent/20">
                <Star className="w-6 h-6 text-accent" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Avg Rating</p>
                <p className="text-3xl font-bold text-foreground">{data.averageRating || '-'}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Charts */}
      <div className="grid lg:grid-cols-2 gap-6">
        {/* Hourly Distribution */}
        <Card className="queue-card">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 font-serif">
              <TrendingUp className="w-5 h-5" />
              Hourly Distribution
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data.hourlyData}>
                  <XAxis dataKey="hour" fontSize={12} />
                  <YAxis fontSize={12} />
                  <Tooltip />
                  <Bar dataKey="count" fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        {/* Transaction Types */}
        <Card className="queue-card">
          <CardHeader>
            <CardTitle className="font-serif">Transaction Types</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={data.transactionBreakdown}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={100}
                    paddingAngle={5}
                    dataKey="value"
                  >
                    {data.transactionBreakdown.map((_, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </div>
            <div className="flex flex-wrap gap-3 justify-center mt-4">
              {data.transactionBreakdown.map((item, index) => (
                <div key={item.name} className="flex items-center gap-2 text-sm">
                  <div 
                    className="w-3 h-3 rounded-full" 
                    style={{ backgroundColor: COLORS[index % COLORS.length] }}
                  />
                  <span className="text-muted-foreground">{item.name}</span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
