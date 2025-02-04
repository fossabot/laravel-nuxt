import { defineStore } from 'pinia'

export interface User {
  name: string
  email: string
  avatar: string
}

export const useUserStore = defineStore('User', {
  state: () => ({
    user: {} as User,
  }),
})
