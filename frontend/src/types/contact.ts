export type Contact = {
  id: number;
  user_id: number;
  name: string;
  email: string | null;
  phone: string | null;
  created_at: string;
  updated_at: string;
};

export type ContactData = {
  name: string;
  email: string | null;
  phone: string | null;
};
