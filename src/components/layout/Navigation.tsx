import { Link, useLocation } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { LayoutDashboard, Ticket, Users, BarChart3, Monitor } from 'lucide-react';

const NAV_ITEMS = [
  { path: '/', label: 'Queue Board', icon: LayoutDashboard },
  { path: '/register', label: 'Get Ticket', icon: Ticket },
  { path: '/staff', label: 'Staff Panel', icon: Users },
  { path: '/analytics', label: 'Analytics', icon: BarChart3 },
  { path: '/display', label: 'Display', icon: Monitor },
];

export function Navigation() {
  const location = useLocation();

  // Hide navigation on display page
  if (location.pathname === '/display') {
    return null;
  }

  return (
    <nav className="sticky top-0 z-50 glass-card border-b">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <Link to="/" className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl gradient-primary flex items-center justify-center">
              <span className="text-primary-foreground font-bold text-lg">U</span>
            </div>
            <span className="font-serif font-bold text-xl text-primary hidden sm:block">
              UniQueue
            </span>
          </Link>

          {/* Nav Items */}
          <div className="flex items-center gap-1">
            {NAV_ITEMS.map(({ path, label, icon: Icon }) => {
              const isActive = location.pathname === path;
              return (
                <Link key={path} to={path}>
                  <Button
                    variant={isActive ? 'default' : 'ghost'}
                    size="sm"
                    className={`gap-2 ${isActive ? 'gradient-primary' : ''}`}
                  >
                    <Icon className="w-4 h-4" />
                    <span className="hidden md:inline">{label}</span>
                  </Button>
                </Link>
              );
            })}
          </div>
        </div>
      </div>
    </nav>
  );
}
