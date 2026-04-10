'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { 
  LayoutDashboard, 
  CreditCard, 
  MapPin, 
  Monitor, 
  Radio, 
  ShieldCheck,
  History,
  Settings,
  LogOut,
  ChevronLeft,
  ChevronRight,
  Package,
  BookOpen
} from 'lucide-react'
import { useState } from 'react'
import { createClient } from '@/lib/supabase/client'
import { useRouter } from 'next/navigation'

const menuItems = [
  { icon: LayoutDashboard, label: 'Dashboard', href: '/' },
  { icon: CreditCard, label: 'Cartões', href: '/cards' },
  { icon: MapPin, label: 'Tags', href: '/tags' },
  { icon: Monitor, label: 'POS', href: '/pos' },
  { icon: Radio, label: 'Rastreadores', href: '/trackers' },
  { type: 'divider' },
  { icon: Package, label: 'Inventário Geral', href: '/inventory' },
  { icon: ShieldCheck, label: 'Procedimentos', href: '/procedures' },
  { icon: BookOpen, label: 'Manuais', href: '/manuals' },
  { type: 'divider' },
  { icon: History, label: 'Histórico Global', href: '/history' },
  { icon: Settings, label: 'Configurações', href: '/settings' },
]

export default function Sidebar() {
  const [collapsed, setCollapsed] = useState(false)
  const pathname = usePathname()
  const router = useRouter()
  const supabase = createClient()

  const handleLogout = async () => {
    await supabase.auth.signOut()
    router.push('/login')
    router.refresh()
  }

  return (
    <aside 
      className={`glass-dark border-r border-white/5 h-screen flex flex-col transition-all duration-300 relative z-20 ${collapsed ? 'w-20' : 'w-64'}`}
    >
      <div className="p-6 flex items-center justify-between">
        {!collapsed && (
          <h1 className="text-xl font-bold tracking-tight text-white animate-in fade-in slide-in-from-left-2">
            FLEX <span className="text-blue-500">GROUP</span>
          </h1>
        )}
        <button 
          onClick={() => setCollapsed(!collapsed)}
          className="p-1 hover:bg-white/5 rounded-lg text-gray-400 hover:text-white transition-colors cursor-pointer"
        >
          {collapsed ? <ChevronRight size={20} /> : <ChevronLeft size={20} />}
        </button>
      </div>

      <nav className="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar">
        {menuItems.map((item, index) => {
          if (item.type === 'divider') {
            return <div key={index} className="h-px bg-white/5 my-4 mx-2" />
          }

          const Icon = item.icon!
          const active = pathname === item.href

          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex items-center gap-4 px-3 py-3 rounded-xl transition-all group ${
                active 
                  ? 'bg-blue-600/20 text-blue-400 border border-blue-500/20 shadow-lg shadow-blue-500/5' 
                  : 'text-gray-400 hover:text-white hover:bg-white/5'
              }`}
            >
              <Icon size={22} className={active ? 'text-blue-400' : 'group-hover:scale-110 transition-transform'} />
              {!collapsed && (
                <span className="text-sm font-medium animate-in fade-in slide-in-from-left-1">
                  {item.label}
                </span>
              )}
            </Link>
          )
        })}
      </nav>

      <div className="p-4 mt-auto">
        <button
          onClick={handleLogout}
          className="flex items-center gap-4 w-full px-3 py-3 text-gray-400 hover:text-red-400 hover:bg-red-500/5 rounded-xl transition-all group cursor-pointer"
        >
          <LogOut size={22} className="group-hover:rotate-12 transition-transform" />
          {!collapsed && (
            <span className="text-sm font-medium">Sair do Sistema</span>
          )}
        </button>
      </div>
    </aside>
  )
}
