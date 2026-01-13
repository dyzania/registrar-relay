import { useState } from 'react';
import { WindowStaff } from '@/components/queue/WindowStaff';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Users } from 'lucide-react';

const Staff = () => {
  const [selectedWindow, setSelectedWindow] = useState<string>('1');

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="max-w-2xl mx-auto space-y-6">
        {/* Window Selector */}
        <Card className="queue-card">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 font-serif">
              <Users className="w-5 h-5" />
              Staff Control Panel
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              <label className="text-sm font-medium text-muted-foreground">
                Select Your Window
              </label>
              <Select value={selectedWindow} onValueChange={setSelectedWindow}>
                <SelectTrigger className="h-12">
                  <SelectValue placeholder="Choose window" />
                </SelectTrigger>
                <SelectContent>
                  {[1, 2, 3, 4].map((num) => (
                    <SelectItem key={num} value={String(num)}>
                      Window {num}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        {/* Window Panel */}
        <WindowStaff windowNumber={parseInt(selectedWindow)} />
      </div>
    </div>
  );
};

export default Staff;
