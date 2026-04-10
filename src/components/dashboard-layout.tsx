'use client'

import Sidebar from '@/components/sidebar'
import { Bell, Search, User } from 'lucide-react'

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <div className="flex h-screen bg-background overflow-hidden">
      <Sidebar />
      
      <div className="flex-1 flex flex-col min-w-0 h-full relative">
        {/* Header Superior */}
        <header className="h-20 border-b border-white/5 flex items-center justify-between px-8 bg-background/50 backdrop-blur-xl z-10">
          <div className="relative w-96 group">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500 group-focus-within:text-blue-500 transition-colors" />
            <input 
              type="text" 
              placeholder="Pesquisar cards, placas ou empresas..." 
              className="w-full bg-white/5 border border-white/10 rounded-xl py-2 pl-10 pr-4 text-sm text-white focus:outline-hidden focus:ring-2 focus:ring-blue-500/50 transition-all"
            />
          </div>

          <div className="flex items-center gap-6">
            <button className="relative p-2 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors cursor-pointer">
              <Bell size={20} />
              <span className="absolute top-2 right-2 w-2 h-2 bg-blue-500 rounded-full border-2 border-background" />
            </button>
            
            <div className="h-8 w-px bg-white/10 mx-2" />

            <div className="flex items-center gap-3 group cursor-pointer p-1 pr-3 hover:bg-white/5 rounded-2xl transition-all">
              <div className="h-10 w-10 rounded-xl bg-linear-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold shadow-lg shadow-blue-500/20">
                AF
              </div>
              <div className="text-left hidden md:block">
                <p className="text-sm font-semibold text-white group-hover:text-blue-400 transition-colors">Admin Flex</p>
                <p className="text-xs text-gray-500">Root Administrator</p>
              </div>
            </div>
          </div>
        </header>

        {/* Área de Conteúdo */}
        <main className="flex-1 overflow-y-auto custom-scrollbar p-8">
          <div className="max-w-7xl mx-auto animate-in fade-in slide-in-from-bottom-2 duration-500">
            {children}
          </div>
        </main>
      </div>
    </div>
  )
}
